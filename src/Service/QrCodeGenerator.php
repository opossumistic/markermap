<?php

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * On-demand SVG QR codes (no persisted assets).
 */
final class QrCodeGenerator
{
    public function svg(string $data, int $size = 320): string
    {
        $builder = new Builder(
            writer: new SvgWriter(),
            writerOptions: [
                SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => false,
                SvgWriter::WRITER_OPTION_COMPACT => true,
            ],
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 12,
        );

        return $builder->build()->getString();
    }
}
