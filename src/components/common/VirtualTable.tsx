/**
 * VirtualTable - LBP Transit
 *
 * Wrapper autour de la Table Ant Design v5 avec virtualisation native.
 * Active le scroll virtuel automatiquement quand la liste dépasse le seuil.
 */

import React, { useState, useEffect, useCallback } from 'react'
import { Table } from 'antd'
import type { ColumnsType } from 'antd/es/table'
import './VirtualTable.css'

/* ═══════════════════════════════════════
   CONSTANTES
═══════════════════════════════════════ */

const VIRTUAL_THRESHOLD = 50
const DEFAULT_SCROLL_Y = 520
const ROW_HEIGHT = 54

/* ═══════════════════════════════════════
   HOOK : hauteur responsive
═══════════════════════════════════════ */

export function useTableHeight(
  minHeight = 300,
  maxHeight = 700,
  offset = 280
): number {
  const [height, setHeight] = useState(DEFAULT_SCROLL_Y)

  const calculate = useCallback(() => {
    const available = window.innerHeight - offset
    setHeight(Math.min(Math.max(available, minHeight), maxHeight))
  }, [minHeight, maxHeight, offset])

  useEffect(() => {
    calculate()
    window.addEventListener('resize', calculate, { passive: true })
    return () => window.removeEventListener('resize', calculate)
  }, [calculate])

  return height
}

/* ═══════════════════════════════════════
   TYPES simplifiés (évite l'ambiguïté namespace antd)
═══════════════════════════════════════ */

export type { ColumnsType }

export interface VirtualTableProps<T> {
  dataSource?: T[]
  columns?: ColumnsType<T>
  rowKey?: string | ((record: T) => string | number)
  loading?: boolean
  /** Pagination Ant Design (objet ou false) */
  pagination?: Record<string, any> | false
  scroll?: { x?: number | string | true; y?: number | string }
  /** Objet locale Ant Design Table (emptyText, etc.) */
  locale?: Record<string, any>
  onRow?: (record: T, index: number) => React.HTMLAttributes<HTMLElement>
  onChange?: (pagination: any, filters: any, sorter: any, extra: any) => void
  rowSelection?: Record<string, any>
  expandable?: Record<string, any>
  summary?: (data: readonly T[]) => React.ReactNode
  className?: string
  size?: 'small' | 'middle' | 'large'
  bordered?: boolean
  rowClassName?: string | ((record: T, index: number) => string)
  showHeader?: boolean
  /** Seuil à partir duquel la virtualisation s'active (défaut : 50) */
  virtualThreshold?: number
  /** Hauteur du viewport virtuel en px (calculée auto si absent) */
  scrollY?: number
  /** Hauteur de chaque ligne en px (défaut : 54) */
  rowHeight?: number
  /** Label pour le "Total : X {totalLabel}" de la pagination */
  totalLabel?: string
}

/* ═══════════════════════════════════════
   COMPOSANT PRINCIPAL
═══════════════════════════════════════ */

export function VirtualTable<T extends object>({
  dataSource,
  columns,
  rowKey,
  loading,
  pagination,
  scroll,
  locale,
  onRow,
  onChange,
  rowSelection,
  expandable,
  summary,
  className = '',
  size,
  bordered,
  rowClassName,
  showHeader,
  virtualThreshold = VIRTUAL_THRESHOLD,
  scrollY,
  rowHeight = ROW_HEIGHT,
  totalLabel = 'éléments',
}: VirtualTableProps<T>) {
  const autoHeight = useTableHeight()

  const count = Array.isArray(dataSource) ? dataSource.length : 0
  const shouldVirtualize = count >= virtualThreshold
  const viewportHeight = scrollY ?? autoHeight

  const mergedScroll = {
    ...scroll,
    ...(shouldVirtualize ? { y: viewportHeight } : {}),
  }

  const mergedPagination =
    pagination === false
      ? (false as const)
      : {
        showSizeChanger: true,
        pageSizeOptions: ['20', '50', '100', '200'],
        showTotal: (total: number) =>
          `Total : ${total.toLocaleString('fr-FR')} ${totalLabel}`,
        ...(typeof pagination === 'object' && pagination !== null
          ? pagination
          : {}),
      }

  const mergedOnRow = (record: T, index: number) => {
    const base = onRow ? onRow(record, index) : {}
    if (!shouldVirtualize) return base
    const existingStyle = (base as React.HTMLAttributes<HTMLElement>).style ?? {}
    return { ...base, style: { ...existingStyle, height: rowHeight } }
  }

  return (
    <div className={`virtual-table-wrapper ${shouldVirtualize ? 'is-virtual' : ''}`}>
      <Table<T>
        dataSource={dataSource}
        columns={columns}
        rowKey={rowKey}
        loading={loading}
        scroll={mergedScroll}
        pagination={mergedPagination}
        locale={locale}
        onRow={mergedOnRow}
        onChange={onChange}
        rowSelection={rowSelection}
        expandable={expandable}
        summary={summary}
        size={size}
        bordered={bordered}
        rowClassName={rowClassName}
        showHeader={showHeader}
        virtual={shouldVirtualize}
        className={`lbp-table ${shouldVirtualize ? 'lbp-table-virtual' : ''} ${className}`}
      />

      {shouldVirtualize && process.env.NODE_ENV === 'development' && (
        <div className="virtual-badge">
          ⚡ {count} lignes virtualisées
        </div>
      )}
    </div>
  )
}

/* ═══════════════════════════════════════
   HOOK : pagination serveur
═══════════════════════════════════════ */

export interface PaginationState {
  page: number
  limit: number
}

/**
 * Gère la pagination serveur.
 *
 * ```tsx
 * const { pagination, antdPagination, resetPage } = usePaginatedTable()
 * // Au changement de filtre → resetPage()
 * const { data } = useQuery({ queryFn: () => service.getAll(pagination) })
 * <VirtualTable
 *   dataSource={data?.data}
 *   pagination={{ ...antdPagination, total: data?.total }}
 * />
 * ```
 */
export function usePaginatedTable(initialPage = 1, initialLimit = 20) {
  const [pagination, setPagination] = useState<PaginationState>({
    page: initialPage,
    limit: initialLimit,
  })

  const resetPage = useCallback(() => {
    setPagination((prev) => ({ ...prev, page: 1 }))
  }, [])

  const antdPagination = {
    current: pagination.page,
    pageSize: pagination.limit,
    onChange: (page: number, pageSize: number) => {
      setPagination({ page, limit: pageSize })
    },
    showSizeChanger: true,
    showQuickJumper: false,
    pageSizeOptions: ['20', '50', '100', '200'],
  }

  return { pagination, setPagination, antdPagination, resetPage }
}
