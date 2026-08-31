# Roteiro de teste de aceitação

> Cartão SH-53. Percorra este roteiro **antes de cada entrega** e antes da
> defesa. Leva cerca de 40 minutos.
>
> O que os testes automatizados (`tests/executar.php`, SH-63) cobrem são as
> funções que erram em silêncio. O que está aqui é o que só se descobre
> usando: um botão que não leva a lugar nenhum, uma tela que abre para quem
> não deveria, um formulário que aceita o que não devia.

## Como usar

- Marque `[x]` no que passou e anote o que falhou.
- **Um item que falha não é "quase certo": é falha.** Registre-o como cartão
  novo no quadro em vez de corrigir no meio do teste.
- Faça o percurso num navegador anônimo. Sessão aberta de um teste anterior
  esconde exatamente os defeitos de autorização que este roteiro procura.

## Preparação

```bash
C:\xampp\mysql\bin\mysql.exe -u root < bd.sql
C:\xampp\php\php.exe tests\executar.php
```

- [ ] O banco foi recriado do zero (`bd.sql`), sem dados de testes anteriores.
- [ ] Apache e MySQL estão no ar no painel do XAMPP.
- [ ] Os 156 testes automatizados passaram.
- [ ] `http://localhost/sporthub_tcc1/` abre a landing page.

---

## A. Site público (visitante, sem login)

| # | Ação | Resultado esperado | OK |
|---|---|---|---|
| A1 | Abrir a página inicial | Carrega sem erro de PHP na tela | [ ] |
| A2 | Percorrer o menu: Como funciona, Planos, Contato | As três abrem e mostram conteúdo | [ ] |
| A3 | Conferir os planos | Os três preços vêm da tabela `planos`, não do HTML | [ ] |
| A4 | Alternar tema claro/escuro pelo botão | Muda na hora, **sem flash branco** ao recarregar | [ ] |
| A5 | Recusar os cookies não essenciais no banner | O banner some e a escolha persiste ao recarregar | [ ] |
| A6 | Abrir Privacidade, Termos e Cookies | Abrem e citam a versão da política | [ ] |
| A7 | Digitar um endereço inexistente (`/xyz.php`) | Página 404 do sistema, não a do Apache | [ ] |
| A8 | Enviar o formulário de contato | Mensagem de confirmação na tela | [ ] |
| A9 | Enviar o contato com e-mail inválido | Recusa com mensagem clara, sem gravar | [ ] |

### A10. Redimensionar para 360 px de largura (SH-46)

- [ ] Nenhuma página rola para o lado.
- [ ] O menu vira menu de celular e abre.
- [ ] Nenhum texto fica cortado ou sobreposto.

### A11. Navegar só pelo teclado (SH-62)

- [ ] `Tab` a partir do topo revela o link "Ir para o conteúdo".
- [ ] **Todo** elemento focado mostra um anel de foco visível.
- [ ] `Enter` aciona os links e botões alcançados.

---

## B. Contratação de assinatura

| # | Ação | Resultado esperado | OK |
|---|---|---|---|
| B1 | Contratar o plano Pro | Formulário abre com o plano certo pré-selecionado | [ ] |
| B2 | Enviar sem aceitar os termos | Recusa; o aceite é obrigatório | [ ] |
| B3 | Enviar preenchido | Código `SH-AAAA-NNNN` exibido na tela | [ ] |
| B4 | Conferir `logs/emails/` | Existe um arquivo com a confirmação (modo sem SMTP) | [ ] |
| B5 | Entrar como admin → Cobranças | Existe uma cobrança pendente com o valor do plano | [ ] |

---

## C. Credenciamento de árbitro

| # | Ação | Resultado esperado | OK |
|---|---|---|---|
| C1 | Abrir `cadastro-arbitro.php` | Formulário completo | [ ] |
| C2 | Informar CPF inválido (`111.111.111-11`) | Recusa com mensagem específica | [ ] |
| C3 | Enviar um `.txt` renomeado para `.pdf` | Recusa: o tipo real é verificado | [ ] |
| C4 | Enviar arquivo acima de 5 MB | Recusa pelo tamanho | [ ] |
| C5 | Enviar tudo válido | Protocolo `ARB-AAAA-NNNN` exibido | [ ] |
| C6 | Consultar pelo protocolo, sem login | Mostra a situação atual | [ ] |
| C7 | Enviar uma segunda solicitação com o mesmo CPF | Recusa: já há uma em análise | [ ] |

---

## D. Coordenação — acesso e senha (SH-48, SH-64, SH-65)

| # | Ação | Resultado esperado | OK |
|---|---|---|---|
| D1 | Entrar com `admin` / `admin1234` | **Desvia para "Defina sua senha"** | [ ] |
| D2 | Tentar navegar direto para `admin/dashboard.php` | Volta para a troca de senha | [ ] |
| D3 | Tentar gravar `admin1234` como senha nova | Recusa: senha de fábrica | [ ] |
| D4 | Tentar gravar `abc123` | Recusa: curta demais | [ ] |
| D5 | Gravar `interclasse2026` | Aceita e libera o painel | [ ] |
| D6 | Sair e entrar com a senha nova | Entra direto no dashboard | [ ] |
| D7 | Errar a senha 5 vezes seguidas | Bloqueia por 5 minutos | [ ] |
| D8 | Abrir "Esqueci minha senha" com e-mail inexistente | **Mesma mensagem** de um e-mail existente | [ ] |
| D9 | Pedir recuperação para um e-mail cadastrado | Arquivo com o link aparece em `logs/emails/` | [ ] |
| D10 | Abrir o link e definir senha nova | Aceita; entra com a nova | [ ] |
| D11 | Abrir o **mesmo** link outra vez | Recusa: uso único | [ ] |

### D12. Segundo fator (SH-65)

- [ ] Menu do usuário → "Verificação em duas etapas" mostra QR Code e chave.
- [ ] O aplicativo autenticador lê o QR **ou** aceita a chave digitada.
- [ ] Um código errado é recusado com mensagem clara.
- [ ] O código correto ativa e exibe **8 códigos de recuperação**.
- [ ] Sair e entrar novamente pede o código de seis dígitos.
- [ ] Fechar a tela do código e ir direto para `admin/dashboard.php` **não entra**.
- [ ] Um dos códigos de recuperação substitui o código do aplicativo.
- [ ] O mesmo código de recuperação **não** funciona uma segunda vez.
- [ ] Desligar o 2FA exige a senha da conta.

---

## E. Coordenação — campeonato

| # | Ação | Resultado esperado | OK |
|---|---|---|---|
| E1 | Cadastrar modalidade "Futsal masculino" | Aparece na lista | [ ] |
| E2 | Cadastrar a mesma de novo | Recusa: duplicada | [ ] |
| E3 | Buscar "fut" na lista de modalidades (SH-83) | Filtra e mostra a contagem | [ ] |
| E4 | Cadastrar 4 times | Aparecem na lista | [ ] |
| E5 | Enviar escudo `.jpg` para um time | Vira PNG em `img/times/` | [ ] |
| E6 | Adicionar jogadores com o mesmo número de camisa | Recusa o repetido | [ ] |
| E7 | Jogos → "Gerar Chaveamento" da modalidade | Cria todos os confrontos, sem data | [ ] |
| E8 | Buscar por nome de time na lista de jogos | Filtra e pagina | [ ] |

### E9. Escala e conflito de horário (SH-38)

- [ ] Escalar árbitro, data, hora e local numa partida: grava.
- [ ] Escalar **o mesmo árbitro** em outra partida no mesmo horário:
      **recusa**, nomeando a partida conflitante.
- [ ] Escalar uma partida com um time que já joga naquele horário: **recusa**.
- [ ] Escalar outra partida no **mesmo local** e horário: **avisa** e permite
      confirmar.
- [ ] O painel lista as partidas ainda sem horário.
- [ ] Remover a designação exige confirmação e funciona.

### E10. Regras do campeonato (SH-56)

- [ ] Mudar a vitória para 2 pontos altera a prévia da classificação na hora.
- [ ] Pôr o empate valendo mais que a vitória é recusado.
- [ ] Mudar a ordem de desempate para "fair play primeiro" reordena a tabela.
- [ ] A mudança aparece também no painel do aluno e na exportação CSV.

### E11. Chaveamento de mata-mata (SH-55)

- [ ] Gerar a chave com 4 classificados cria semifinal e final.
- [ ] O 1º colocado enfrenta o último classificado.
- [ ] Com 3 classificados, o 1º recebe passagem direta.
- [ ] "Agendar partida" cria o jogo real em `admin/jogos.php`.
- [ ] Encerrar essa partida com vencedor **promove o time** à fase seguinte.
- [ ] Encerrar empatada **não promove ninguém** e mostra o aviso.

---

## F. Arbitragem — súmula

| # | Ação | Resultado esperado | OK |
|---|---|---|---|
| F1 | Entrar como árbitro | Vê **apenas** as partidas designadas a ele | [ ] |
| F2 | Alterar o `jogo_id` na URL para uma partida de outro árbitro | Recusa | [ ] |
| F3 | Registrar placar 3 × 1 | Grava e volta ao painel | [ ] |
| F4 | Lançar 3 gols com nome de jogador e minuto | Grava os eventos | [ ] |
| F5 | Lançar um cartão amarelo e um vermelho | Gravam | [ ] |
| F6 | Reabrir a súmula | **Os eventos lançados continuam lá** | [ ] |
| F7 | Gravar de novo sem mexer | Não duplica os eventos | [ ] |
| F8 | Baixar "Súmula em PDF" (SH-45) | Abre no leitor; acentos corretos | [ ] |
| F9 | Conferir o PDF | Traz placar, eventos, observações e linhas de assinatura | [ ] |

---

## G. Aluno — consulta

| # | Ação | Resultado esperado | OK |
|---|---|---|---|
| G1 | Criar conta de aluno pela tela de login | Cria e pede login | [ ] |
| G2 | Criar com o mesmo usuário | Recusa: já existe | [ ] |
| G3 | Entrar e abrir Jogos | Calendário com as partidas | [ ] |
| G4 | Abrir Classificação | Ordem coerente com as regras configuradas | [ ] |
| G5 | Conferir um time de nome acentuado | Aparece na posição alfabética certa | [ ] |
| G6 | Abrir "Atletas" (SH-67) | Artilharia com os gols lançados em F4 | [ ] |
| G7 | Aba "Fair play" | Cartões lançados em F5 | [ ] |
| G8 | Exportar classificação em CSV | Abre no Excel com acentos e colunas corretos | [ ] |
| G9 | Copiar o endereço do calendário `.ics` | Assina no Google Agenda e mostra os jogos | [ ] |
| G10 | Abrir o `.ics` trocando um caractere do token | Recusa com 403 | [ ] |
| G11 | Abrir o telão | Alterna entre placar e tabela; `F` entra em tela cheia | [ ] |

### G12. O aluno **não** pode ver (autorização)

Estando logado como aluno, abrir cada endereço abaixo deve **redirecionar ou
recusar**:

- [ ] `admin/dashboard.php`
- [ ] `admin/contas_lgpd.php`
- [ ] `admin/ocorrencias.php`
- [ ] `admin/galeria.php`
- [ ] `arbitro/registrar_resultado.php?jogo_id=1`
- [ ] `arbitro/sumula_pdf.php?jogo_id=1`

---

## H. LGPD

| # | Ação | Resultado esperado | OK |
|---|---|---|---|
| H1 | Abrir `lgpd.php` sem login | Formulário do titular acessível | [ ] |
| H2 | Pedir acesso aos dados | Protocolo `LGPD-AAAA-NNNN` e prazo de 15 dias | [ ] |
| H3 | Consultar pelo protocolo | Mostra a situação | [ ] |
| H4 | Como admin, ver Portal LGPD | Requisição listada, ordenada por prazo | [ ] |
| H5 | Responder a requisição | Titular vê a resposta pelo protocolo | [ ] |

### H6. Contas e titulares (SH-60)

- [ ] "Exportar dados (JSON)" baixa um arquivo legível com os dados da conta.
- [ ] Anonimizar uma conta de aluno remove nome, e-mail, CPF e telefone.
- [ ] A conta anonimizada **não consegue mais entrar**, mesmo com a senha certa.
- [ ] Anonimizar uma conta de árbitro **preserva as súmulas** dele.
- [ ] Eliminar de vez uma conta com vínculos é **recusado**, explicando por quê.
- [ ] A tela recusa anonimizar a **própria** conta do administrador logado.
- [ ] A tela recusa anonimizar o **último** administrador ativo.

### H7. Galeria (SH-71)

- [ ] Foto enviada **sem** marcar o consentimento entra como restrita.
- [ ] Foto restrita **não aparece** no mural do aluno.
- [ ] Publicar uma foto sem consentimento registrado é recusado.
- [ ] Foto com prazo de guarda vencido sai do mural do aluno sozinha.
- [ ] "Eliminar" apaga o arquivo de `uploads/galeria/` de verdade.

---

## I. Segurança

| # | Ação | Resultado esperado | OK |
|---|---|---|---|
| I1 | Abrir o console do navegador em cada tela | **Zero** erros de Content Security Policy | [ ] |
| I2 | Conferir o cabeçalho CSP | Traz `nonce-` e **não** traz `unsafe-inline` | [ ] |
| I3 | Cadastrar time chamado `<script>alert(1)</script>` | Aparece como texto; nenhum alerta dispara | [ ] |
| I4 | Enviar um formulário com o token CSRF alterado | Recusa | [ ] |
| I5 | Abrir `uploads/credenciamento/<arquivo>` direto | Recusa (403) | [ ] |
| I6 | Abrir `includes/config.php` direto | Recusa | [ ] |
| I7 | Abrir `logs/php-error.log` direto | Recusa | [ ] |
| I8 | Abrir `bd.sql` direto | Recusa | [ ] |
| I9 | Ficar 2 h sem atividade e recarregar | Sessão expirada, com aviso na tela de login | [ ] |

---

## J. Operação

- [ ] `scripts\backup.bat` gera a pasta datada com o `.sql` e `uploads/`.
- [ ] O `.sql` gerado tem mais de zero bytes e o histórico registra `OK`.
- [ ] `scripts\restaurar.bat <pasta> olimpiasp_teste` restaura em banco de teste.
- [ ] Depois de restaurar, as contagens de usuários, times e jogos conferem.
- [ ] O dashboard mostra o cartão de pendências de produção enquanto
      `config.local.php` não existir.

---

## Registro da execução

| Data | Versão / sprint | Executado por | Itens falhos | Cartões abertos |
|---|---|---|---|---|
| | | | | |
| | | | | |
