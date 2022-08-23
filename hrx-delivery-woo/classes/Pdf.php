<?php
namespace HrxDeliveryWoo;

// Prevent direct access to this script
if ( ! defined('ABSPATH') ) {
    exit;
}

use \setasign\Fpdi\Fpdi;

class Pdf
{
    public static function merge_pdfs( $pdf_files )
    {
        $page_count = 0;

        $pdf = new Fpdi();

        foreach ( $pdf_files as $file ) {
            $page_count = $pdf->setSourceFile($file);
            for ( $page_no = 1; $page_no <= $page_count; $page_no++ ) {
                $template_id = $pdf->importPage($page_no);
                $pdf->AddPage('P');
                $pdf->useTemplate($template_id, ['adjustPageSize' => true]);
            }
        }

        return base64_encode($pdf->Output('S'));
    }
}
