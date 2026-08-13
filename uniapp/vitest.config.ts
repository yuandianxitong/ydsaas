import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'

export default defineConfig({
    resolve: {
        alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) }
    },
    server: {
        fs: {
            // modules/shop 软链指向 server/plugins/shop/uniapp,realpath 在项目根之外
            allow: ['..']
        }
    },
    test: {
        environment: 'node',
        include: ['src/**/*.spec.ts']
    }
})
