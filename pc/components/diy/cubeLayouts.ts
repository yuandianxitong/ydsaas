/** 图片魔方固定排版（与 uniapp/pc 同构，修改时请三端同步） */

export interface CubeCell {
    /** CSS grid-column，如 "1 / 2" 或 "1 / 3" */
    column: string
    /** CSS grid-row */
    row: string
}

export interface CubeLayoutDef {
    id: string
    label: string
    slots: number
    columns: number
    rows: number
    cells: CubeCell[]
}

export const CUBE_LAYOUTS: CubeLayoutDef[] = [
    {
        id: 'row-2',
        label: '一行二列',
        slots: 2,
        columns: 2,
        rows: 1,
        cells: [
            { column: '1 / 2', row: '1 / 2' },
            { column: '2 / 3', row: '1 / 2' },
        ],
    },
    {
        id: 'row-3',
        label: '一行三列',
        slots: 3,
        columns: 3,
        rows: 1,
        cells: [
            { column: '1 / 2', row: '1 / 2' },
            { column: '2 / 3', row: '1 / 2' },
            { column: '3 / 4', row: '1 / 2' },
        ],
    },
    {
        id: 'row-4',
        label: '一行四列',
        slots: 4,
        columns: 4,
        rows: 1,
        cells: [
            { column: '1 / 2', row: '1 / 2' },
            { column: '2 / 3', row: '1 / 2' },
            { column: '3 / 4', row: '1 / 2' },
            { column: '4 / 5', row: '1 / 2' },
        ],
    },
    {
        id: 'grid-2x2',
        label: '二行二列',
        slots: 4,
        columns: 2,
        rows: 2,
        cells: [
            { column: '1 / 2', row: '1 / 2' },
            { column: '2 / 3', row: '1 / 2' },
            { column: '1 / 2', row: '2 / 3' },
            { column: '2 / 3', row: '2 / 3' },
        ],
    },
    {
        id: 'left1-right2',
        label: '左大右二',
        slots: 3,
        columns: 2,
        rows: 2,
        cells: [
            { column: '1 / 2', row: '1 / 3' },
            { column: '2 / 3', row: '1 / 2' },
            { column: '2 / 3', row: '2 / 3' },
        ],
    },
    {
        id: 'left2-right1',
        label: '左二右大',
        slots: 3,
        columns: 2,
        rows: 2,
        cells: [
            { column: '1 / 2', row: '1 / 2' },
            { column: '1 / 2', row: '2 / 3' },
            { column: '2 / 3', row: '1 / 3' },
        ],
    },
    {
        id: 'top1-bottom2',
        label: '上大下二',
        slots: 3,
        columns: 2,
        rows: 2,
        cells: [
            { column: '1 / 3', row: '1 / 2' },
            { column: '1 / 2', row: '2 / 3' },
            { column: '2 / 3', row: '2 / 3' },
        ],
    },
    {
        id: 'top2-bottom1',
        label: '上二下大',
        slots: 3,
        columns: 2,
        rows: 2,
        cells: [
            { column: '1 / 2', row: '1 / 2' },
            { column: '2 / 3', row: '1 / 2' },
            { column: '1 / 3', row: '2 / 3' },
        ],
    },
    {
        id: 'left1-right3',
        label: '左大右三',
        slots: 4,
        columns: 2,
        rows: 3,
        cells: [
            { column: '1 / 2', row: '1 / 4' },
            { column: '2 / 3', row: '1 / 2' },
            { column: '2 / 3', row: '2 / 3' },
            { column: '2 / 3', row: '3 / 4' },
        ],
    },
    {
        id: 'top1-bottom3',
        label: '上大下三',
        slots: 4,
        columns: 3,
        rows: 2,
        cells: [
            { column: '1 / 4', row: '1 / 2' },
            { column: '1 / 2', row: '2 / 3' },
            { column: '2 / 3', row: '2 / 3' },
            { column: '3 / 4', row: '2 / 3' },
        ],
    },
    {
        id: 'l-shape',
        label: 'L 形四格',
        slots: 4,
        columns: 2,
        rows: 3,
        cells: [
            { column: '1 / 2', row: '1 / 3' },
            { column: '2 / 3', row: '1 / 2' },
            { column: '2 / 3', row: '2 / 3' },
            { column: '1 / 3', row: '3 / 4' },
        ],
    },
]

const BY_ID = new Map(CUBE_LAYOUTS.map((l) => [l.id, l]))

export function getCubeLayout(id?: string | null): CubeLayoutDef | null {
    if (!id) return null
    return BY_ID.get(String(id)) || null
}

export function ensureCubeItems(
    items: Array<{ image?: string; link?: string; title?: string }> | undefined,
    slots: number
): Array<{ image: string; link: string; title: string }> {
    const src = Array.isArray(items) ? items : []
    const next = []
    for (let i = 0; i < slots; i++) {
        const it = src[i] || {}
        next.push({
            image: String(it.image || ''),
            link: String(it.link || ''),
            title: String(it.title || ''),
        })
    }
    return next
}
