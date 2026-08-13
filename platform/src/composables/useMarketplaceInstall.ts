import { type InstallResolution, marketplaceApi } from '@/api/marketplace'

import { useMarketplaceOauth } from './useMarketplaceOauth'

/**
 * 安装意图编排（前端侧）。
 *
 * - 已连接：resolveDirect() 直接向后端要判定结果（后端 cache 优先，miss 才同步）。
 * - 未连接：connectAndResolve() 走 OAuth —— 调用方先在点击同步栈里 openPopup()，
 *   再交给本方法导航、兑换；兑换成功后后端已自动同步并 resolve，install_resolution 一并返回。
 */
export function useMarketplaceInstall() {
    const { openPopup, awaitAuthCode } = useMarketplaceOauth()
    // 回调地址必须带上应用 base(/platform/)，否则 Site 跳回后路由匹配不到。
    // 用 BASE_URL 派生，避免写死前缀（base 改了也不会断）。
    const callbackUrl =
        window.location.origin +
        import.meta.env.BASE_URL.replace(/\/$/, '') +
        '/marketplace/oauth-callback'

    async function resolveDirect(appCode: string): Promise<InstallResolution> {
        const res = await marketplaceApi.createInstallIntent({ app_code: appCode })
        return res.data
    }

    /**
     * @param appCode 目标应用；null 表示纯连接（连接账号 / 更换账号）
     * @param popup   调用方已在点击同步栈里打开的窗口
     */
    async function connectAndResolve(
        appCode: string | null,
        popup: Window
    ): Promise<InstallResolution> {
        try {
            const res = await marketplaceApi.createInstallIntent({
                app_code: appCode ?? undefined,
                callback_url: callbackUrl
            })
            const intent = res.data

            if (intent.status !== 'authorization_required') {
                popup.close()
                return intent
            }

            const { state, code } = await awaitAuthCode(popup, intent.authorize_url)
            const ex = await marketplaceApi.exchange({ state, code })
            return ex.data.install_resolution ?? { status: 'connected' }
        } catch (e) {
            try {
                popup.close()
            } catch {
                /* popup 可能已被用户关闭 */
            }
            throw e
        }
    }

    return { openPopup, resolveDirect, connectAndResolve }
}
