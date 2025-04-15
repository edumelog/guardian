# Guardian - Sistema de Gerenciamento de Visitantes

<p align="center">
  <img src="public/images/logo.svg" alt="Guardian Logo" width="300"/>
</p>

## Sobre o Projeto

O Guardian é um Sistema de Gerenciamento de Visitantes (VMS - Visitor Management System) desenvolvido pela equipe de Inovação Tecnológica da DTI - Diretoria de Tecnologia da Informação da [Câmara Municipal do Rio de Janeiro](https://camara.rio).

Este sistema permite o cadastro de visitantes, captura de dados pessoais e fotos para impressão de credenciais em impressoras térmicas, além de gerenciar todo o fluxo de entrada e saída de visitantes nas dependências da instituição.

## Principais Funcionalidades

- Cadastro completo de visitantes
- Captura de fotos via webcam
- Geração de QR Codes e códigos de barras para identificação rápida
- Impressão de credenciais em impressoras térmicas
- Gerenciamento de templates de credenciais personalizáveis
- Controle de entrada e saída de visitantes
- Registro de ocorrências de segurança com níveis de severidade
- Relatórios avançados com exportação para PDF e CSV
- Histórico de visitas
- Painel administrativo completo
- Proteção de dados sensíveis dos visitantes

## Tecnologias Utilizadas

O Guardian foi desenvolvido utilizando um stack moderno de tecnologias:

- **[Laravel 11](https://laravel.com/)**: Framework PHP para o backend
- **[Filament 3](https://filamentphp.com/)**: Framework de administração para Laravel
- **[Filament Shield](https://github.com/bezhansalleh/filament-shield)**: Gerenciamento de permissões para Filament
- **[Laravel Breeze](https://laravel.com/docs/11.x/starter-kits#laravel-breeze)**: Kit inicial para autenticação
- **[Alpine.js](https://alpinejs.dev/)**: Framework JavaScript para interatividade
- **[QZ Tray](https://qz.io/)**: Biblioteca para impressão
- **[JsBarcode](https://github.com/lindell/JsBarcode)**: Geração de códigos de barras
- **[QRious](https://github.com/neocotic/qrious)**: Geração de QR codes

## Requisitos do Sistema

- PHP 8.2 ou superior
- Composer
- Node.js e NPM
- Banco de dados compatível com Laravel (MySQL, PostgreSQL, SQLite)
- Servidor web (Nginx, Apache)

## Histórico de Versões

### v1.5.4
- Substituição do path do Node e Chrome para valores default para uso pelo Browsershot

### v1.5.3
- Restrição de deleção de usuários que tenham ocorrências em seu nome ou editadas por ele
- Possibilidade de desabilitar um usuário do sistema impedindo o login

### v1.5.2
- Implementação da coluna "Modificado-por" nos relatórios
- Restrição de deleção de destinos e tipos de documentos com visitantes associados

### v1.5.1
- **Implementação do uso de template usando %/vw/vh e sem referencia fixa de dimensão da etiqueta** - principal funcionalidade desta versão
- Criação de ícones e letras para os dias da semana
- Implementação da funcionalidade de Backups (Full) com opçãoo de download e restore

### v1.5.0
- **Implementação do Registro Automático de Ocorrências para Restrições Comuns de Acesso** - principal funcionalidade desta versão
- Aprimoramento do sistema de Restrições de Acesso com múltiplos níveis de severidade (none, low, medium, high)
- Novo fluxo de aprovação simplificado para visitantes com restrições, agora exibindo contagem por nível de severidade
- Suporte a múltiplas restrições desativadas para o mesmo visitante com validação aprimorada
- Correção da formatação de datas em relatórios para o padrão brasileiro (dd/mm/aaaa)
- Implementação de validação avançada para períodos de pesquisa nos relatórios de segurança
- Melhoria na interface com valores padrão para campos de data vazios nos formulários de pesquisa
- Otimização da experiência do usuário na validação de formulários de restrições

### v1.4.0
- Implementação do módulo "Registro de Ocorrências" para registrar eventos de segurança
- Melhorias nos relatórios de visitantes com opções avançadas de exportação em PDF e CSV
- Correção da exibição de datas em relatórios PDF para formato brasileiro
- Reestruturação da interface de pesquisa com layout em colunas
- Validação avançada para períodos de data e hora nos relatórios

### v1.2.0
- Melhorias na interface do usuário
- Correções de bugs relacionados à validação de formulários
- Otimização de desempenho em relatórios com grandes volumes de dados

### v1.1.0
- Implementação de QR code para identificação rápida
- Implementação de código de barras para leitura com scanner
- Melhorias na conversão de templates
- Integração com scanner de mesa para leitura rápida de credenciais

### v1.0.3
- Hotfix: Correção das substituições nos templates de credenciais

### v1.0.2
- Hotfix: Permite apagar qualquer template de credencial

### v1.0.1
- Hotfix: Informações dos visitantes protegidas no disco privado para maior segurança

### v1.0.0
- Versão inicial do sistema
- Cadastro de visitantes
- Captura de fotos
- Impressão de credenciais
- Gerenciamento de templates

## Instalação e Configuração

```bash
# Clone o repositório
git clone https://github.com/camara-rio/guardian.git

# Entre no diretório do projeto
cd guardian

# Instale as dependências do PHP
composer install

# Instale as dependências do JavaScript
npm install

# Compile os assets
npm run build

# Configure o arquivo de ambiente
cp .env.example .env
php artisan key:generate

# Execute as migrações
php artisan migrate

# Crie um usuário administrador
php artisan shield:super-admin
```

## Uso

Após a instalação, acesse o sistema através do navegador e faça login com as credenciais do administrador criado.

O sistema permite:
1. Cadastrar visitantes
2. Capturar fotos
3. Gerar credenciais
4. Controlar entrada e saída
5. Gerenciar templates de credenciais
6. Configurar impressoras

## Contribuição

Contribuições são bem-vindas! Para contribuir:

1. Faça um fork do projeto
2. Crie uma branch para sua feature (`git checkout -b feature/nova-funcionalidade`)
3. Faça commit das suas alterações (`git commit -m 'Adiciona nova funcionalidade'`)
4. Faça push para a branch (`git push origin feature/nova-funcionalidade`)
5. Abra um Pull Request

## Autor

Eduardo Melo - [eduardo.melo@camara.rj.gov.br](mailto:eduardo.melo@camara.rj.gov.br)

## Licença

Este projeto é propriedade da Câmara Municipal do Rio de Janeiro.