/**
 * 只允许 http(s) 绝对地址、站内相对路径(/...) 与锚点(#...)。
 * 拒绝 javascript:/data:/vbscript: 等危险 scheme（返回空串 → 调用方不渲染 <a>）。
 */
export function safeLink(raw?: string): string {
  if (!raw) return ''
  const s = String(raw).trim()
  if (s.startsWith('/') || s.startsWith('#')) return s
  if (/^https?:\/\//i.test(s)) return s
  return ''
}
