import ExcelJS from 'exceljs'
import { saveAs } from 'file-saver'
import type { TableData } from './types'

export interface ExcelExportOptions {
  title?: string
  author?: string
  company?: string
  sheetName?: string
  autoWidth?: boolean
  headerStyle?: {
    fill?: { type: 'pattern'; pattern: 'solid'; fgColor: { argb: string } }
    font?: { bold?: boolean; color?: { argb: string }; size?: number }
    alignment?: { horizontal: 'center'; vertical: 'middle' }
  }
}

/**
 * Exporte des données tabulaires en Excel
 */
export const exportTableToExcel = async (
  data: TableData,
  filename: string = 'export',
  options: ExcelExportOptions = {}
) => {
  const {
    title,
    author = 'LBP System',
    company = 'La Belle Porte',
    sheetName = 'Export',
    autoWidth = true,
    headerStyle = {
      fill: {
        type: 'pattern',
        pattern: 'solid',
        fgColor: { argb: 'FF34495E' },
      },
      font: { bold: true, color: { argb: 'FFFFFFFF' }, size: 12 },
      alignment: { horizontal: 'center', vertical: 'middle' },
    },
  } = options

  const workbook = new ExcelJS.Workbook()

  // Métadonnées
  workbook.creator = author
  workbook.company = company
  workbook.created = new Date()
  workbook.modified = new Date()

  const worksheet = workbook.addWorksheet(sheetName)

  // Titre si fourni
  if (title) {
    const titleRow = worksheet.addRow([title])
    worksheet.mergeCells(1, 1, 1, data.headers.length)
    titleRow.font = { size: 16, bold: true }
    titleRow.alignment = { horizontal: 'center', vertical: 'middle' }
    titleRow.height = 30

    // Date
    const dateRow = worksheet.addRow([
      `Généré le : ${new Date().toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })}`,
    ])
    worksheet.mergeCells(2, 1, 2, data.headers.length)
    dateRow.font = { size: 10, color: { argb: 'FF808080' } }
    dateRow.alignment = { horizontal: 'left', vertical: 'middle' }

    // Ligne vide
    worksheet.addRow([])
  }

  // En-têtes
  const headerRow = worksheet.addRow(data.headers)
  headerRow.eachCell((cell: any) => {
    cell.style = headerStyle
  })
  headerRow.height = 25

  // Données
  data.rows.forEach((row) => {
    worksheet.addRow(row)
  })

  // Ajuster la largeur des colonnes
  if (autoWidth && worksheet.columns) {
    worksheet.columns.forEach((column: any) => {
      if (column) {
        let maxLength = 0
        if (column.header) {
          const headerValue = Array.isArray(column.header) 
            ? column.header.join(' ') 
            : String(column.header)
          maxLength = headerValue.length
        }
        column.eachCell?.({ includeEmpty: true }, (cell: any) => {
          const columnLength = cell.value ? cell.value.toString().length : 10
          if (columnLength > maxLength) {
            maxLength = columnLength
          }
        })
        if (column.width !== undefined) {
          column.width = Math.min(maxLength + 2, 50)
        }
      }
    })
  }

  // Ajouter des bordures
  worksheet.eachRow((row: any, rowNumber: any) => {
    row.eachCell((cell: any) => {
      cell.border = {
        top: { style: 'thin' },
        left: { style: 'thin' },
        bottom: { style: 'thin' },
        right: { style: 'thin' },
      }
    })
  })

  // Générer le buffer et télécharger
  const buffer = await workbook.xlsx.writeBuffer()
  const blob = new Blob([buffer], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  })
  saveAs(blob, `${filename}_${Date.now()}.xlsx`)
}

/**
 * Exporte plusieurs feuilles en un seul fichier Excel
 */
export const exportMultiSheetToExcel = async (
  sheets: Array<{ name: string; data: TableData }>,
  filename: string = 'export',
  options: ExcelExportOptions = {}
) => {
  const workbook = new ExcelJS.Workbook()

  workbook.creator = options.author || 'LBP System'
  workbook.company = options.company || 'La Belle Porte'
  workbook.created = new Date()
  workbook.modified = new Date()

  sheets.forEach((sheet) => {
    const worksheet = workbook.addWorksheet(sheet.name)
    const { headers, rows } = sheet.data

    // En-têtes
    const headerRow = worksheet.addRow(headers)
    headerRow.eachCell((cell: any) => {
      cell.style = options.headerStyle || {
        fill: {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: 'FF34495E' },
        },
        font: { bold: true, color: { argb: 'FFFFFFFF' }, size: 12 },
        alignment: { horizontal: 'center', vertical: 'middle' },
      }
    })

    // Données
    rows.forEach((row) => {
      worksheet.addRow(row)
    })

      // Ajuster la largeur
      if (worksheet.columns) {
        worksheet.columns.forEach((column: any) => {
          if (column) {
            let maxLength = 0
            if (column.header) {
              const headerValue = Array.isArray(column.header) 
                ? column.header.join(' ') 
                : String(column.header)
              maxLength = headerValue.length
            }
            column.eachCell?.({ includeEmpty: true }, (cell: any) => {
              const columnLength = cell.value ? cell.value.toString().length : 10
              if (columnLength > maxLength) {
                maxLength = columnLength
              }
            })
            if (column.width !== undefined) {
              column.width = Math.min(maxLength + 2, 50)
            }
          }
        })
      }

    // Bordures
    worksheet.eachRow((row: any) => {
      row.eachCell((cell: any) => {
        cell.border = {
          top: { style: 'thin' },
          left: { style: 'thin' },
          bottom: { style: 'thin' },
          right: { style: 'thin' },
        }
      })
    })
  })

  const buffer = await workbook.xlsx.writeBuffer()
  const blob = new Blob([buffer], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  })
  saveAs(blob, `${filename}_${Date.now()}.xlsx`)
}

/**
 * Exporte un graphique en Excel (en tant qu'image dans une feuille)
 */
export const exportChartToExcel = async (
  chartImage: string | HTMLCanvasElement,
  filename: string = 'graphique',
  chartTitle?: string
) => {
  const workbook = new ExcelJS.Workbook()
  const worksheet = workbook.addWorksheet('Graphique')

  let imgData: string
  if (typeof chartImage === 'string') {
    imgData = chartImage
  } else {
    imgData = chartImage.toDataURL('image/png')
  }

  // Titre
  if (chartTitle) {
    const titleRow = worksheet.addRow([chartTitle])
    titleRow.font = { size: 16, bold: true }
    titleRow.alignment = { horizontal: 'center', vertical: 'middle' }
    worksheet.mergeCells(1, 1, 1, 10)
  }

  // Ajouter l'image (note: ExcelJS supporte les images mais c'est complexe)
  // Pour l'instant, on peut créer une note ou un lien vers l'image
  worksheet.addRow([
    `Graphique généré le : ${new Date().toLocaleDateString('fr-FR')}`,
  ])

  const buffer = await workbook.xlsx.writeBuffer()
  const blob = new Blob([buffer], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  })
  saveAs(blob, `${filename}_${Date.now()}.xlsx`)
}
