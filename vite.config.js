import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Detecta se estamos em produção (npm run build/prod) ou desenvolvimento (npm run dev)
const isProduction = process.env.NODE_ENV === 'production';
// Detecta se estamos usando Traefik com SSL em desenvolvimento
const useTraefik = process.env.VITE_USE_TRAEFIK === 'true';

export default defineConfig({
    // Configuração do servidor de desenvolvimento
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'guardian.dev.dti',
            // Em produção sempre usa wss, em dev depende do Traefik
            protocol: isProduction || useTraefik ? 'wss' : 'ws',
            // Em produção ou com Traefik usa 443, caso contrário usa 5173
            clientPort: isProduction || useTraefik ? 443 : 5173,
        },
        // cors: {
        //     origin: '*',
        //     credentials: true,
        // },
    },
    
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
