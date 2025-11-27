<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;

/**
 * Create a new Excel spreadsheet with modern styling
 * 
 * @return Spreadsheet
 */
function create_excel_spreadsheet()
{
    return new Spreadsheet();
}

/**
 * Add modern header styling to a worksheet
 * 
 * @param Spreadsheet $spreadsheet
 * @param array $headers Column headers
 * @param int $row Row number (default 1)
 * @return void
 */
function add_excel_header($spreadsheet, $headers, $row = 1)
{
    $sheet = $spreadsheet->getActiveSheet();
    
    foreach ($headers as $index => $header) {
        $column = chr(65 + $index); // A, B, C, etc.
        $cell = $sheet->getCell($column . $row);
        $cell->setValue($header);
        
        // Modern header styling
        $style = $cell->getStyle();
        $fontColor = new \PhpOffice\PhpSpreadsheet\Style\Color();
        $fontColor->setARGB('FFFFFFFF');
        $style->getFont()->setSize(12)->setBold(true)->setColor($fontColor);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        
        // Add border
        $style->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('1E40AF');
    }
}

/**
 * Add data row with alternating colors
 * 
 * @param Spreadsheet $spreadsheet
 * @param array $data Row data
 * @param int $row Row number
 * @param bool $isAlternate Use alternating color (default false)
 * @param array $centeredColumns Column indices to center align (default [])
 * @return void
 */
function add_excel_row($spreadsheet, $data, $row, $isAlternate = false, $centeredColumns = [])
{
    $sheet = $spreadsheet->getActiveSheet();
    
    foreach ($data as $index => $value) {
        $column = chr(65 + $index);
        $cell = $sheet->getCell($column . $row);
        $cell->setValue($value);
        
        $style = $cell->getStyle();
        
        // Alternating row colors
        if ($isAlternate) {
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F9FF');
        } else {
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        }
        
        // Text styling
        $fontColor = new \PhpOffice\PhpSpreadsheet\Style\Color();
        $fontColor->setARGB('FF1F2937');
        $style->getFont()->setSize(11)->setColor($fontColor);
        
        // Alignment
        if (in_array($index, $centeredColumns)) {
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        } else {
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        
        // Border
        $style->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');
    }
}

/**
 * Add title section at top
 * 
 * @param Spreadsheet $spreadsheet
 * @param string $title Title text
 * @param int $row Row number (default 1)
 * @param int $mergeUntil Number of columns to merge (default 6)
 * @return void
 */
function add_excel_title($spreadsheet, $title, $row = 1, $mergeUntil = 6)
{
    $sheet = $spreadsheet->getActiveSheet();
    $titleCell = $sheet->getCell('A' . $row);
    $titleCell->setValue($title);
    
    // Merge cells
    $colLetter = chr(64 + $mergeUntil);
    $sheet->mergeCells("A{$row}:{$colLetter}{$row}");
    
    $style = $titleCell->getStyle();
    $fontColor = new \PhpOffice\PhpSpreadsheet\Style\Color();
    $fontColor->setARGB('FF1E3A8A');
    $style->getFont()->setSize(16)->setBold(true)->setColor($fontColor);
    $style->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
}

/**
 * Add subtitle/metadata section
 * 
 * @param Spreadsheet $spreadsheet
 * @param string $label Label text
 * @param string $value Value text
 * @param int $row Row number
 * @param int $mergeUntil Number of columns to merge (default 6)
 * @return void
 */
function add_excel_subtitle($spreadsheet, $label, $value, $row, $mergeUntil = 6)
{
    $sheet = $spreadsheet->getActiveSheet();
    $cell = $sheet->getCell('A' . $row);
    $cell->setValue("{$label}: {$value}");
    
    $colLetter = chr(64 + $mergeUntil);
    $sheet->mergeCells("A{$row}:{$colLetter}{$row}");
    
    $style = $cell->getStyle();
    $fontColor = new \PhpOffice\PhpSpreadsheet\Style\Color();
    $fontColor->setARGB('FF6B7280');
    $style->getFont()->setSize(10)->setColor($fontColor);
    $style->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER);
}

/**
 * Add summary/total row
 * 
 * @param Spreadsheet $spreadsheet
 * @param array $data Row data
 * @param int $row Row number
 * @param string $bgColor Background color hex (default 1E40AF)
 * @return void
 */
function add_excel_summary_row($spreadsheet, $data, $row, $bgColor = '1E40AF')
{
    $sheet = $spreadsheet->getActiveSheet();
    
    foreach ($data as $index => $value) {
        $column = chr(65 + $index);
        $cell = $sheet->getCell($column . $row);
        $cell->setValue($value);
        
        $style = $cell->getStyle();
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bgColor);
        $fontColor = new \PhpOffice\PhpSpreadsheet\Style\Color();
        $fontColor->setARGB('FFFFFFFF');
        $style->getFont()->setSize(11)->setBold(true)->setColor($fontColor);
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        
        $style->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('1E40AF');
    }
}

/**
 * Set column widths
 * 
 * @param Spreadsheet $spreadsheet
 * @param array $widths Array of column => width pairs (e.g., ['A' => 12, 'B' => 15])
 * @return void
 */
function set_excel_column_widths($spreadsheet, $widths)
{
    $sheet = $spreadsheet->getActiveSheet();
    
    foreach ($widths as $column => $width) {
        $sheet->getColumnDimension($column)->setWidth($width);
    }
}

/**
 * Set row height
 * 
 * @param Spreadsheet $spreadsheet
 * @param int $row Row number
 * @param float $height Height in points
 * @return void
 */
function set_excel_row_height($spreadsheet, $row, $height)
{
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->getRowDimension($row)->setRowHeight($height);
}

/**
 * Freeze panes
 * 
 * @param Spreadsheet $spreadsheet
 * @param string $cell Cell to freeze at (default A2)
 * @return void
 */
function freeze_excel_pane($spreadsheet, $cell = 'A2')
{
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->freezePane($cell);
}

/**
 * Add auto filter
 * 
 * @param Spreadsheet $spreadsheet
 * @param string $range Filter range (default A1:Z100)
 * @return void
 */
function add_excel_autofilter($spreadsheet, $range = 'A1:Z100')
{
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setAutoFilter($range);
}

/**
 * Download Excel file
 * 
 * @param Spreadsheet $spreadsheet
 * @param string $filename Filename for download
 * @return void
 */
function download_excel_file($spreadsheet, $filename = 'export.xlsx')
{
    $writer = new Xlsx($spreadsheet);
    
    // Set headers
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;
}

/**
 * Save Excel file to disk
 * 
 * @param Spreadsheet $spreadsheet
 * @param string $filepath Full file path to save
 * @return void
 */
function save_excel_file($spreadsheet, $filepath)
{
    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);
}
