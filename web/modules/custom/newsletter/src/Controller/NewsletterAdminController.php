<?php

namespace Drupal\newsletter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controlador del área de administración del Newsletter.
 */
class NewsletterAdminController extends ControllerBase {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Listado de suscriptores con acciones de editar/eliminar y botones de exportación.
   */
  public function listSubscribers() {
    $header = [
      ['data' => $this->t('ID'),       'field' => 'id',      'sort' => 'desc'],
      ['data' => $this->t('Nombre'),   'field' => 'name'],
      ['data' => $this->t('Email'),    'field' => 'email'],
      ['data' => $this->t('Estado'),   'field' => 'status'],
      ['data' => $this->t('Fecha alta'), 'field' => 'created'],
      ['data' => $this->t('Acciones')],
    ];

    $query = $this->database->select('newsletter_subscribers', 'n')
      ->fields('n')
      ->extend('\Drupal\Core\Database\Query\TableSortExtender')
      ->orderByHeader($header)
      ->extend('\Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(25);

    $rows = [];
    foreach ($query->execute() as $row) {
      $edit_url   = Url::fromRoute('newsletter.admin_edit',   ['id' => $row->id]);
      $delete_url = Url::fromRoute('newsletter.admin_delete', ['id' => $row->id]);

      $rows[] = [
        $row->id,
        $row->name ?: '—',
        $row->email,
        $row->status ? $this->t('Activo') : $this->t('Inactivo'),
        date('d/m/Y H:i', $row->created),
        [
          'data' => [
            '#type'  => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Editar'),
                'url'   => $edit_url,
              ],
              'delete' => [
                'title'      => $this->t('Eliminar'),
                'url'        => $delete_url,
              ],
            ],
          ],
        ],
      ];
    }

    // Contador total.
    $total = $this->database->select('newsletter_subscribers', 'n')
      ->countQuery()->execute()->fetchField();

    $build['summary'] = [
      '#markup' => '<p>' . $this->t('Total de suscriptores: <strong>@total</strong>', ['@total' => $total]) . '</p>',
    ];

    // Botones de exportación.
    $build['export'] = [
      '#type'  => 'container',
      '#attributes' => ['class' => ['newsletter-export-buttons'], 'style' => 'margin-bottom:1em;'],
    ];

    $build['export']['csv'] = [
      '#type'  => 'link',
      '#title' => $this->t('⬇ Exportar CSV'),
      '#url'   => Url::fromRoute('newsletter.export_csv'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $build['export']['excel'] = [
      '#type'  => 'link',
      '#title' => $this->t('⬇ Exportar Excel'),
      '#url'   => Url::fromRoute('newsletter.export_excel'),
      '#attributes' => ['class' => ['button'], 'style' => 'margin-left:0.5em;'],
    ];

    $build['table'] = [
      '#type'   => 'table',
      '#header' => $header,
      '#rows'   => $rows,
      '#empty'  => $this->t('No hay suscriptores aún.'),
    ];

    $build['pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Exporta todos los suscriptores en formato CSV.
   */
  public function exportCsv() {
    $subscribers = $this->getAllSubscribers();

    $response = new StreamedResponse(function () use ($subscribers) {
      $handle = fopen('php://output', 'w');

      // BOM para UTF-8 (compatibilidad con Excel).
      fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

      // Cabecera.
      fputcsv($handle, ['ID', 'Nombre', 'Email', 'Estado', 'Fecha de alta'], ';');

      foreach ($subscribers as $row) {
        fputcsv($handle, [
          $row->id,
          $row->name,
          $row->email,
          $row->status ? 'Activo' : 'Inactivo',
          date('d/m/Y H:i', $row->created),
        ], ';');
      }

      fclose($handle);
    });

    $filename = 'newsletter_suscriptores_' . date('Ymd_His') . '.csv';
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }

  /**
   * Exporta todos los suscriptores en formato Excel (XLSX) nativo.
   *
   * Genera un XLSX real sin dependencias externas usando el formato
   * XML de SpreadsheetML (Office Open XML simplificado).
   */
  public function exportExcel() {
    $subscribers = $this->getAllSubscribers();

    // Construir el XLSX manualmente (formato XML compatible con Excel/Calc).
    $rows = [];
    $rows[] = ['ID', 'Nombre', 'Email', 'Estado', 'Fecha de alta'];

    foreach ($subscribers as $row) {
      $rows[] = [
        $row->id,
        $row->name,
        $row->email,
        $row->status ? 'Activo' : 'Inactivo',
        date('d/m/Y H:i', $row->created),
      ];
    }

    $xlsx = $this->buildXlsx($rows);

    $filename = 'newsletter_suscriptores_' . date('Ymd_His') . '.xlsx';

    $response = new Response($xlsx);
    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    $response->headers->set('Cache-Control', 'max-age=0');

    return $response;
  }

  /**
   * Obtiene todos los suscriptores de la base de datos.
   */
  protected function getAllSubscribers() {
    return $this->database->select('newsletter_subscribers', 'n')
      ->fields('n')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll();
  }

  /**
   * Construye un archivo XLSX real (Office Open XML) sin librerías externas.
   *
   * @param array $rows
   *   Array bidimensional con los datos (primera fila = cabeceras).
   *
   * @return string
   *   Contenido binario del fichero .xlsx.
   */
  protected function buildXlsx(array $rows): string {
    // Escape XML helper.
    $x = fn($v) => htmlspecialchars((string) $v, ENT_XML1, 'UTF-8');

    // Shared strings (todas las celdas como strings para simplicidad).
    $strings = [];
    $stringIndex = [];
    $sheetXmlRows = '';

    foreach ($rows as $rowIndex => $row) {
      $sheetXmlRows .= '<row r="' . ($rowIndex + 1) . '">';
      foreach ($row as $colIndex => $cell) {
        $colLetter = $this->columnLetter($colIndex);
        $cellRef   = $colLetter . ($rowIndex + 1);
        $cellVal   = (string) $cell;

        if (!isset($stringIndex[$cellVal])) {
          $stringIndex[$cellVal] = count($strings);
          $strings[] = $cellVal;
        }

        $si = $stringIndex[$cellVal];
        $sheetXmlRows .= '<c r="' . $cellRef . '" t="s"><v>' . $si . '</v></c>';
      }
      $sheetXmlRows .= '</row>';
    }

    // Shared strings XML.
    $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
    foreach ($strings as $s) {
      $ssXml .= '<si><t xml:space="preserve">' . $x($s) . '</t></si>';
    }
    $ssXml .= '</sst>';

    // Sheet XML.
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
      . '<sheetData>' . $sheetXmlRows . '</sheetData>'
      . '</worksheet>';

    // Workbook XML.
    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
      . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
      . '<sheets><sheet name="Suscriptores" sheetId="1" r:id="rId1"/></sheets>'
      . '</workbook>';

    // Relationships.
    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
      . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
      . '</Relationships>';

    $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
      . '</Relationships>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
      . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
      . '<Default Extension="xml" ContentType="application/xml"/>'
      . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
      . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
      . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
      . '</Types>';

    // Crear ZIP en memoria.
    $tmpFile = tempnam(sys_get_temp_dir(), 'newsletter_xlsx_');
    $zip = new \ZipArchive();
    $zip->open($tmpFile, \ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml',              $contentTypes);
    $zip->addFromString('_rels/.rels',                     $relsXml);
    $zip->addFromString('xl/workbook.xml',                 $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels',      $workbookRels);
    $zip->addFromString('xl/worksheets/sheet1.xml',        $sheetXml);
    $zip->addFromString('xl/sharedStrings.xml',            $ssXml);

    $zip->close();

    $content = file_get_contents($tmpFile);
    unlink($tmpFile);

    return $content;
  }

  /**
   * Convierte un índice de columna (0-based) a letras de Excel (A, B, ..., Z, AA, ...).
   */
  protected function columnLetter(int $index): string {
    $letters = '';
    $index++;
    while ($index > 0) {
      $index--;
      $letters = chr(65 + ($index % 26)) . $letters;
      $index   = (int) ($index / 26);
    }
    return $letters;
  }

}
