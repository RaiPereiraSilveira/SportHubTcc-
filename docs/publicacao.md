# Publicação em produção

> Cartão SH-43. Passo a passo para tirar o SportHub do XAMPP e colocá-lo num
> servidor de verdade.
>
> **O que o código resolve e o que não resolve.** Todas as constantes,
> scripts e verificações estão prontos. O que falta é o que ninguém escreve
> em PHP: contratar hospedagem, apontar um domínio e emitir um certificado.
> Este documento é a lista do que fazer, na ordem, com o que já está pronto
> em cada passo.

---

## Por que HTTPS não é opcional aqui

Não é preferência de segurança. Três funcionalidades do sistema **não
funcionam** em `http://`:

| Funcionalidade | O que acontece em http |
|---|---|
| Cookie de sessão `Secure` | Não é marcado; a sessão trafega em texto puro |
| `Strict-Transport-Security` | Não é enviado |
| PWA / instalar no celular (SH-69) | O navegador **recusa** registrar o service worker |

O código já trata os três: o cookie vira `Secure` sozinho quando detecta
HTTPS, o cabeçalho HSTS passa a ser enviado, e o registro do service worker é
condicionado ao protocolo. Nada precisa ser editado — precisa apenas ser
servido por HTTPS.

---

## Requisitos do servidor

| Item | Mínimo | Observação |
|---|---|---|
| PHP | 8.0 | Extensões: `pdo_mysql`, `fileinfo`, `mbstring`, `openssl`, `gd`, `iconv` |
| MySQL / MariaDB | 5.7 / 10.4 | A migração usa `ADD COLUMN IF NOT EXISTS` (MariaDB) |
| Apache | 2.4 | `AllowOverride All` e os módulos `deflate`, `filter`, `expires`, `headers` |
| Disco | 1 GB | Uploads de documento e galeria crescem com o uso |
| Certificado | Let's Encrypt | Gratuito e renovável automaticamente |

> **`AllowOverride All` é crítico.** Sem ele o Apache ignora o `.htaccess`, e
> com ele vão embora o bloqueio de execução em `uploads/`, o bloqueio de
> `logs/` e a página 404 do sistema. Confirme com a hospedagem antes de
> contratar.

### Módulos do Apache

O `.htaccess` traz compressão e cache de estáticos, mas os dois blocos ficam
inertes se os módulos não estiverem carregados. No `httpd.conf`:

```apache
LoadModule deflate_module modules/mod_deflate.so
LoadModule filter_module  modules/mod_filter.so
LoadModule expires_module modules/mod_expires.so
LoadModule headers_module modules/mod_headers.so
```

**A pegadinha é o `mod_filter`.** A diretiva `AddOutputFilterByType` pertence a
ele, não ao `mod_deflate` — e o `<IfModule mod_deflate.c>` do `.htaccess` passa
mesmo sem ele. O resultado é um **HTTP 500 em todas as páginas**, com
`Invalid command 'AddOutputFilterByType'` no log de erro. Ou os dois, ou nenhum.

O ganho medido no XAMPP local, somando as folhas de estilo, os scripts e as
imagens de uma visita à página inicial:

| | Sem os módulos | Com os módulos |
|---|---:|---:|
| Primeira visita | 283 KB | **88 KB** |
| `css/style.css` | 88.652 B | **18.310 B** |
| `js/sporthub-ui.js` | 12.481 B | **4.235 B** |
| Revisita | 283 KB | **~7 KB** (o resto vem do cache) |

---

## Passo a passo

### 1. Enviar os arquivos

Envie o projeto inteiro **exceto**:

```
logs/*.log        logs/emails/      logs/segredo_feed.txt
uploads/*         includes/config.local.php
```

`logs/` e `uploads/` precisam existir e ter permissão de escrita:

```bash
mkdir -p logs/emails uploads/profile_photos uploads/credenciamento uploads/galeria img/times
chmod 750 logs uploads
chmod -R 750 uploads/*
```

### 2. Criar o banco e o usuário dedicado (SH-49)

```bash
mysql -u root -p < bd.sql
```

Abra `scripts/usuario_banco.sql`, **troque a senha de exemplo** por uma
aleatória (`php -r "echo bin2hex(random_bytes(16));"`) e rode:

```bash
mysql -u root -p < scripts/usuario_banco.sql
```

Isso cria `sporthub_app`, que só faz SELECT, INSERT, UPDATE e DELETE no banco
do projeto. Sem DROP, sem GRANT, sem acesso aos outros bancos do servidor. Se
a aplicação for invadida por uma falha, o estrago fica contido.

> Atualizando uma instalação existente? Rode as migrações na ordem:
> `scripts/migration_v2.sql` e depois `scripts/migration_v3.sql`.

### 3. Criar `includes/config.local.php`

```bash
cp includes/config.local.example.php includes/config.local.php
```

Preencha o que se aplica. **Nada aqui é obrigatório** — o que ficar de fora
mantém o padrão de fábrica — mas em produção os quatro primeiros blocos são:

| Bloco | Constantes | Por quê |
|---|---|---|
| Banco (SH-49) | `SH_DB_USUARIO`, `SH_DB_SENHA` | Sair do `root` |
| Segredo do feed (SH-84) | `SH_SEGREDO_FEED` | Só se houver mais de um servidor |
| E-mail (SH-42) | `SH_SMTP_*` | Sem isso, nada é entregue |
| Controlador (SH-44) | `SH_CONTROLADOR_*`, `SH_DPO_*` | Exigência da LGPD, arts. 9º e 41 |

> **O arquivo nunca vai para o repositório.** Ele guarda a senha do banco e a
> do SMTP. O `.htaccess` também nega o acesso direto a ele, para o caso de o
> módulo PHP estar desligado por algum motivo.

### 4. Trocar as senhas e remover as contas de demonstração (SH-48)

```bash
php scripts/preparar_producao.php
```

O script remove as contas `arbitro`, `professor` e `aluno`, define uma senha
nova para a coordenação e lista o que ainda falta configurar.

Sem rodar o script, o sistema **não fica inseguro** — as contas de fábrica
nascem com `senha_provisoria = 1` e o login exige a troca antes de liberar
qualquer tela. Mas as contas de demonstração continuariam existindo, e não é
isso que se quer numa escola.

### 5. Ligar o HTTPS

Com o certificado emitido, abra o `.htaccess` e **descomente o bloco final**:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} !=on
    RewriteCond %{HTTP:X-Forwarded-Proto} !https
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</IfModule>
```

A segunda condição existe para hospedagem atrás de proxy (Cloudflare, balan-
ceador): ali o Apache recebe a requisição em http mesmo com o visitante em
https, e sem essa linha o site entra em laço de redirecionamento.

Com Let's Encrypt:

```bash
sudo certbot --apache -d interclasse.suaescola.edu.br
```

O certbot instala a renovação automática. Confira com `certbot renew --dry-run`.

### 6. Ajustar o caminho das páginas de erro

O `.htaccess` traz o caminho da pasta de desenvolvimento:

```apache
ErrorDocument 404 /sporthub_tcc1/404.php
ErrorDocument 403 /sporthub_tcc1/404.php
```

Se o site ficar na raiz do domínio, troque para `/404.php`. Sem isso o Apache
não encontra o arquivo e devolve um 404 de corpo vazio — foi exatamente o
defeito do cartão SH-78.

### 7. Agendar o backup (SH-51)

```bash
crontab -e
```

```
0 2 * * * /var/www/sporthub/scripts/backup.sh >> /var/log/sporthub-backup.log 2>&1
```

Confira no dia seguinte se o arquivo apareceu e se tem tamanho plausível.
**Backup que nunca foi restaurado não é backup**: teste a restauração num
banco de teste uma vez por bimestre.

### 8. Conferir

Percorra `docs/roteiro-teste-aceitacao.md` no ambiente publicado. Além dele:

- [ ] `https://` funciona e `http://` redireciona.
- [ ] O cadeado do navegador aparece sem aviso de conteúdo misto.
- [ ] O console do navegador não mostra erro de Content Security Policy.
- [ ] `https://seudominio/includes/config.local.php` → recusa.
- [ ] `https://seudominio/logs/php-error.log` → recusa.
- [ ] `https://seudominio/bd.sql` → recusa.
- [ ] `https://seudominio/uploads/credenciamento/<arquivo>` → recusa.
- [ ] Uma mensagem de teste chega de verdade (confira em Gestão →
      Comunicações).
- [ ] No celular, o navegador oferece "Adicionar à tela de início".

---

## O que continua dependendo de terceiros

Registrado com franqueza — são os cartões que permanecem em "Bloqueado" e o
motivo de cada um.

### Gateway de pagamento (SH-41)

O código está pronto em `includes/pagamento.php`: criação de cobrança, baixa
com registro de quem confirmou, e verificação de assinatura HMAC do webhook.
Falta a credencial de uma adquirente aprovada e um endereço público em HTTPS
para receber a notificação.

**Enquanto isso**, o modo `pix` funciona hoje e sem contrato nenhum: o sistema
monta o "copia e cola" do Pix a partir da chave da escola, no padrão BR Code
do Banco Central. O banco não avisa o sistema quando o código é pago — a baixa
continua manual, e é exatamente essa parte que exige a integração.

### Notificação push (SH-69)

O PWA instala e funciona offline. A notificação de início de partida exige um
servidor capaz de assinar mensagens VAPID e de guardar as inscrições dos
aparelhos. É um serviço à parte, não uma linha de código faltando.

### Dados jurídicos do controlador (SH-44)

Razão social, CNPJ, endereço e o nome do encarregado precisam vir da escola.
Enquanto forem os de fábrica, o painel marca a pendência e os documentos legais
exibem "a preencher" em vez de um dado inventado — que seria pior do que a
lacuna.
