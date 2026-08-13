export interface OauthCode {
    state: string
    code: string
}

/**
 * 官方市场 OAuth 弹窗的底层机制。
 *
 * 关键：openPopup() 必须在用户点击的同步调用栈里立即调用，先打开 about:blank，
 * 避免「请求 initiate 完成后才 window.open」被浏览器当作非用户手势而拦截。
 * 拿到 authorize_url 后再用 awaitAuthCode() 把这个已打开的窗口导航过去。
 */
export function useMarketplaceOauth() {
    function openPopup(): Window | null {
        return window.open('about:blank', 'marketplace-oauth', 'width=600,height=700')
    }

    function awaitAuthCode(popup: Window, authorizeUrl: string): Promise<OauthCode> {
        popup.location.href = authorizeUrl

        return new Promise<OauthCode>((resolve, reject) => {
            const cleanup = () => {
                window.removeEventListener('message', onMessage)
                window.clearInterval(timer)
            }
            const onMessage = (ev: MessageEvent) => {
                if (ev.origin !== window.location.origin) return
                if (!ev.data || ev.data.type !== 'marketplace-oauth') return
                cleanup()
                resolve({ state: ev.data.state, code: ev.data.code })
            }
            const timer = window.setInterval(() => {
                if (popup.closed) {
                    window.clearInterval(timer)
                    // 回调页先 postMessage 再 close，给在途消息一个事件循环的时间先 resolve，
                    // 避免把"已授权但窗口刚关"误判为取消。已 resolve 时此 reject 为空操作。
                    window.setTimeout(() => {
                        cleanup()
                        reject(new Error('授权窗口已关闭，流程取消'))
                    }, 250)
                }
            }, 500)
            window.addEventListener('message', onMessage)
        })
    }

    return { openPopup, awaitAuthCode }
}
