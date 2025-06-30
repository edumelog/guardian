import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Detecta se estamos usando VITE e Traefik com SSL em desenvolvimento
const useTraefik = process.env.VITE_USE_TRAEFIK === 'true';

const devHost = useTraefik ? 'v-' + process.env.APP_NAME + '.' + process.env.APP_DOMAIN : process.env.APP_NAME + '.' + process.env.APP_DOMAIN;
// Se usar Traefik, usa 443, caso contrário, usa 5173
const clientPort = useTraefik ? 443 : 5173;

export default defineConfig({
    base: '',
    // Configuração do servidor de desenvolvimento
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: devHost,
            // Se usar Traefik, usa wss, caso contrário, usa ws
            protocol: useTraefik ? 'wss' : 'ws',
            clientPort: clientPort,
        },
        cors: {
            origin: '*',
            credentials: true,
        },
    },
    
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
