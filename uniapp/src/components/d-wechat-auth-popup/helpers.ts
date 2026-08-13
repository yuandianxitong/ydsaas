/** 保存按钮可用判定：昵称必填；头像必填；强绑模式须已授权手机号；loading 互斥 */
export function canSubmit(nickname: string, avatarPath: string, showPhone: boolean, phoneDisplay: string, loading: boolean): boolean {
  if (loading) return false
  if (nickname.trim() === '') return false
  if (avatarPath === '') return false
  if (showPhone && phoneDisplay === '') return false
  return true
}

/** submit 载荷：avatar 为空不带键（后端 request->only 语义，空串会覆盖已有头像） */
export function buildSubmitPayload(nickname: string, avatarPath: string): { nickname: string; avatar?: string } {
  const payload: { nickname: string; avatar?: string } = { nickname: nickname.trim() }
  if (avatarPath !== '') payload.avatar = avatarPath
  return payload
}

/** 手机号 344 脱敏；非 11 位原样返回 */
export function maskMobile(mobile: string): string {
  if (mobile.length !== 11) return mobile
  return `${mobile.slice(0, 3)}****${mobile.slice(7)}`
}
