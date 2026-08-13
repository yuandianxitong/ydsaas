import { get } from '~/composables/useRequest'

export interface ArticleCategory {
  id: number
  name: string
  sort: number
}

export interface ArticleItem {
  id: number
  title: string
  cover: string
  summary: string
  content: string
  category_id: number
  category_name?: string
  author: string
  tags: string[] | string
  views: number
  is_top: number
  status: number
  published_at: string
  created_at: string
}

export interface PageResult<T> {
  list: T[]
  total: number
  page_no: number
  page_size: number
}

export const articleApi = {
  getList: (params?: { page_no?: number; page_size?: number; category_id?: number }) =>
    get<PageResult<ArticleItem>>('/api/cms/article/list', params),

  getDetail: (id: number | string) =>
    get<ArticleItem>(`/api/cms/article/detail/${id}`),

  getCategoryList: () =>
    get<ArticleCategory[]>('/api/cms/article-category/list'),
}
