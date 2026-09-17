<?php

declare(strict_types=1);

namespace Modules\Reference\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Reference\Domain\Enums\ReferenceEntity;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\ImportBatch;

/**
 * BE-R11 — EP-AD-041. A CSV of one reference entity, validated row by row.
 *
 * A dry run validates everything and returns the preview rows plus every error as
 * `{row, column, message}` — and writes nothing, not even a batch row. Execution runs
 * inside one transaction and refuses the whole file when any row is invalid, so a
 * half-imported file cannot exist. Re-executing under the same idempotency key replays
 * the stored response without touching the tables again (that is the middleware's job,
 * and the batch-level test proves it end to end).
 *
 * Rows are matched on the entity's natural key — `code` for governorates,
 * `(governorate_code, name)` for zones, `name` for the rest — so a re-import of the same
 * file updates rather than duplicates. `status` is never a column: an import cannot
 * disable anything (rule 12, and only the status routes may).
 */
final class ImportReferences
{
    /**
     * Columns per entity: required first, optional after.
     *
     * @var array<string, array{required: list<string>, optional: list<string>}>
     */
    private const COLUMNS = [
        'governorates' => ['required' => ['code', 'name_ar', 'name_en'], 'optional' => ['order']],
        'zones' => ['required' => ['governorate_code', 'name'], 'optional' => ['district', 'order']],
        'activity_types' => ['required' => ['name'], 'optional' => ['icon', 'description', 'order']],
        'root_categories' => ['required' => ['name'], 'optional' => ['icon', 'image', 'order']],
        'sale_units' => ['required' => ['name'], 'optional' => ['abbr', 'default_factor']],
        'equipments' => ['required' => ['name'], 'optional' => ['icon', 'description', 'order']],
    ];

    private const INTEGER_COLUMNS = ['order', 'default_factor'];

    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @return array{
     *     dry_run: bool,
     *     type: string,
     *     rows_total: int,
     *     preview: list<array<string, mixed>>,
     *     errors: list<array{row: int, column: string, message: string}>,
     *     batch_id?: int,
     *     rows_created?: int,
     *     rows_updated?: int
     * }
     */
    public function __invoke(ReferenceEntity $entity, string $csv, bool $dryRun, ?object $actor, ?string $fileName = null): array
    {
        $type = $entity->value;
        $spec = self::COLUMNS[$type] ?? null;
        if ($spec === null) {
            return $this->result($type, $dryRun, 0, [], [['row' => 0, 'column' => 'type', 'message' => __('reference.import_unknown_type')]]);
        }

        [$header, $records] = $this->parse($csv);
        if ($header === []) {
            return $this->result($type, $dryRun, 0, [], [['row' => 1, 'column' => 'file', 'message' => __('reference.import_empty_file')]]);
        }

        $errors = [];
        foreach ($spec['required'] as $column) {
            if (! in_array($column, $header, true)) {
                $errors[] = ['row' => 1, 'column' => $column, 'message' => __('reference.import_missing_column')];
            }
        }
        if ($errors !== []) {
            return $this->result($type, $dryRun, count($records), [], $errors);
        }

        $governorates = $type === 'zones'
            ? Governorate::query()->pluck('id', 'code')->map(fn ($id) => (int) $id)->all()
            : [];

        $preview = [];
        $seen = [];
        foreach ($records as $i => $record) {
            $rowNo = $i + 2; // 1 is the header
            $row = [];
            foreach ([...$spec['required'], ...$spec['optional']] as $column) {
                $row[$column] = array_key_exists($column, $record) ? trim((string) $record[$column]) : null;
            }

            foreach ($spec['required'] as $column) {
                if ($row[$column] === null || $row[$column] === '') {
                    $errors[] = ['row' => $rowNo, 'column' => $column, 'message' => __('reference.import_required')];
                }
            }
            foreach (self::INTEGER_COLUMNS as $column) {
                if (($row[$column] ?? '') !== '' && $row[$column] !== null && preg_match('/^\d+$/', $row[$column]) !== 1) {
                    $errors[] = ['row' => $rowNo, 'column' => $column, 'message' => __('reference.import_invalid_integer')];
                }
            }
            foreach ($row as $column => $value) {
                if (is_string($value) && mb_strlen($value) > 191) {
                    $errors[] = ['row' => $rowNo, 'column' => $column, 'message' => __('reference.import_too_long')];
                }
            }

            $key = $this->naturalKey($type, $row);
            if ($key !== null) {
                if (isset($seen[$key])) {
                    $errors[] = ['row' => $rowNo, 'column' => $this->keyColumn($type), 'message' => __('reference.import_duplicate_in_file')];
                }
                $seen[$key] = $rowNo;
            }

            if ($type === 'zones' && ($row['governorate_code'] ?? '') !== '' && ! isset($governorates[$row['governorate_code']])) {
                $errors[] = ['row' => $rowNo, 'column' => 'governorate_code', 'message' => __('reference.import_governorate_not_found')];
            }

            $preview[] = ['row' => $rowNo] + $row;
        }

        if ($dryRun || $errors !== []) {
            return $this->result($type, $dryRun, count($records), $preview, $errors);
        }

        $counts = DB::transaction(function () use ($entity, $type, $preview, $governorates): array {
            $created = 0;
            $updated = 0;
            $class = $entity->modelClass();

            foreach ($preview as $row) {
                unset($row['row']);
                $attributes = $this->attributes($type, $row, $governorates);
                $match = $this->matchAttributes($type, $row, $governorates);

                $existing = $class::query()->where($match)->first();
                if ($existing !== null) {
                    $existing->fill($attributes)->save();
                    $updated++;
                } else {
                    $class::query()->create($attributes);
                    $created++;
                }
            }

            return ['created' => $created, 'updated' => $updated];
        });

        $batch = ImportBatch::query()->create([
            'type' => $type,
            'file_name' => $fileName,
            'file_hash' => hash('sha256', $csv),
            'rows_total' => count($records),
            'rows_created' => $counts['created'],
            'rows_updated' => $counts['updated'],
            'rows_failed' => 0,
            'errors' => [],
            'actor_type' => $actor !== null ? $actor::class : null,
            'actor_id' => $actor !== null && method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null,
        ]);

        $this->audit->record('ref.import.execute', $actor, 'import_batch', (int) $batch->id, [
            'type' => $type,
            'rows_total' => count($records),
            'rows_created' => $counts['created'],
            'rows_updated' => $counts['updated'],
        ]);

        return $this->result($type, false, count($records), $preview, []) + [
            'batch_id' => (int) $batch->id,
            'rows_created' => $counts['created'],
            'rows_updated' => $counts['updated'],
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<array<string, string>>}
     */
    private function parse(string $csv): array
    {
        $csv = ltrim($csv, "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $lines = array_values(array_filter($lines, fn (string $l) => trim($l) !== ''));
        if ($lines === []) {
            return [[], []];
        }

        $header = array_map(fn (string $h) => strtolower(trim($h)), str_getcsv(array_shift($lines)));
        $records = [];
        foreach ($lines as $line) {
            $cells = str_getcsv($line);
            $record = [];
            foreach ($header as $i => $column) {
                $record[$column] = $cells[$i] ?? '';
            }
            $records[] = $record;
        }

        return [$header, $records];
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function naturalKey(string $type, array $row): ?string
    {
        return match ($type) {
            'governorates' => ($row['code'] ?? '') !== '' ? 'code:'.mb_strtoupper((string) $row['code']) : null,
            'zones' => ($row['name'] ?? '') !== '' ? ($row['governorate_code'] ?? '').'|'.$row['name'] : null,
            default => ($row['name'] ?? '') !== '' ? 'name:'.$row['name'] : null,
        };
    }

    private function keyColumn(string $type): string
    {
        return match ($type) {
            'governorates' => 'code',
            default => 'name',
        };
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, int>  $governorates
     * @return array<string, mixed>
     */
    private function attributes(string $type, array $row, array $governorates): array
    {
        $attributes = [];
        foreach ($row as $column => $value) {
            if ($column === 'governorate_code') {
                $attributes['governorate_id'] = $governorates[(string) $value] ?? null;

                continue;
            }
            if ($value === null || $value === '') {
                if (in_array($column, self::INTEGER_COLUMNS, true)) {
                    continue; // keep the model default
                }
                $attributes[$column] = null;

                continue;
            }
            $attributes[$column] = in_array($column, self::INTEGER_COLUMNS, true) ? (int) $value : $value;
        }
        if ($type === 'governorates' && isset($attributes['code'])) {
            $attributes['code'] = mb_strtoupper((string) $attributes['code']);
        }

        return $attributes;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, int>  $governorates
     * @return array<string, mixed>
     */
    private function matchAttributes(string $type, array $row, array $governorates): array
    {
        return match ($type) {
            'governorates' => ['code' => mb_strtoupper((string) $row['code'])],
            'zones' => ['governorate_id' => $governorates[(string) $row['governorate_code']] ?? 0, 'name' => $row['name']],
            default => ['name' => $row['name']],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $preview
     * @param  list<array{row: int, column: string, message: string}>  $errors
     * @return array{dry_run: bool, type: string, rows_total: int, preview: list<array<string, mixed>>, errors: list<array{row: int, column: string, message: string}>}
     */
    private function result(string $type, bool $dryRun, int $total, array $preview, array $errors): array
    {
        return [
            'dry_run' => $dryRun,
            'type' => $type,
            'rows_total' => $total,
            'preview' => $preview,
            'errors' => $errors,
        ];
    }
}
