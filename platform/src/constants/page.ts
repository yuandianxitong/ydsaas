export enum PageEnum {
    //登录页面
    LOGIN = '/login',
    //无权限页面
    ERROR_403 = '/403',
    //页面不存在
    ERROR_404 = '/404',
    //服务器错误页面
    ERROR_500 = '/500',
    INDEX = '/',
    REDIRECT = '/redirect',
    //官方市场 OAuth 回调（弹窗页，base-relative，无需登录）
    MARKETPLACE_OAUTH_CALLBACK = '/marketplace/oauth-callback'
}
