<?php

declare(strict_types=1);

namespace Modules\Finance\Infrastructure;

use Illuminate\Support\Facades\Storage;

final class SettlementReceiptPdf
{
    /**
     * @param  array{rep_id: int, amount: int, operation_no: string}  $data
     */
    public function write(int $settlementId, array $data): string
    {
        $path = 'settlements/'.$settlementId.'.pdf';
        $body = "%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
            .'trailer<</Root 1 0 R>>'."\n%%EOF\n".$data['operation_no'];

        Storage::disk('local')->put($path, $body);

        return '/storage/'.$path;
    }
}
