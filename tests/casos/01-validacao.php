<?php
/**
 * tests/casos/01-validacao.php — CPF, senha, escape de busca, dinheiro.
 *
 * Funções que decidem se um dado entra no sistema. Erram em silêncio: um CPF
 * inválido aceito só aparece quando a secretaria tenta emitir a credencial.
 */

grupo('Validação de CPF (sh_cpf_valido)');

// CPFs com dígitos verificadores corretos.
verificar('aceita CPF válido 529.982.247-25',  sh_cpf_valido('529.982.247-25'));
verificar('aceita o mesmo CPF sem pontuação',  sh_cpf_valido('52998224725'));
verificar('aceita CPF válido 111.444.777-35',  sh_cpf_valido('111.444.777-35'));

// Erros que a validação precisa pegar.
verificar('recusa dígito verificador errado',  !sh_cpf_valido('529.982.247-26'));
verificar('recusa CPF com todos os dígitos iguais', !sh_cpf_valido('111.111.111-11'));
verificar('recusa CPF curto',                  !sh_cpf_valido('1234567890'));
verificar('recusa CPF longo',                  !sh_cpf_valido('123456789012'));
verificar('recusa texto',                      !sh_cpf_valido('não é um cpf'));
verificar('recusa string vazia',               !sh_cpf_valido(''));

grupo('Máscara de CPF (sh_mascarar_cpf)');

igual('mascara mantendo só o miolo', '***.982.247-**', sh_mascarar_cpf('529.982.247-25'));
igual('CPF incompleto vira ***',     '***',            sh_mascarar_cpf('123'));

grupo('Política de senha (sh_senha_politica)');

igual('aceita senha com letra e número', '', sh_senha_politica('interclasse2026'));
igual('aceita exatamente 8 caracteres',  '', sh_senha_politica('abcd1234x'));

verificar('recusa senha curta',
    sh_senha_politica('abc123') !== '');
verificar('recusa senha só de letras',
    sh_senha_politica('senhasemnumero') !== '');
verificar('recusa senha só de números',
    sh_senha_politica('123456789') !== '');
verificar('recusa senha que contém o usuário',
    sh_senha_politica('admin12345', 'admin') !== '');
verificar('recusa a senha de fábrica admin1234',
    sh_senha_politica('admin1234') !== '');
verificar('recusa a senha de fábrica aluno1234',
    sh_senha_politica('aluno1234') !== '');
verificar('recusa senha absurdamente longa',
    sh_senha_politica(str_repeat('a1', 200)) !== '');

grupo('Escape do LIKE na busca (sh_termo_like)');

igual('envolve o termo em curingas', '%leões%', sh_termo_like('leões'));
igual('escapa o % digitado pelo usuário', '%50\%%', sh_termo_like('50%'));
igual('escapa o sublinhado',              '%a\_b%', sh_termo_like('a_b'));
igual('escapa a contrabarra',             '%a\\\\b%', sh_termo_like('a\\b'));
igual('termo vazio não vira "traga tudo"', '', sh_termo_like('   '));

grupo('Formatação de valores (sh_money)');

igual('milhar com ponto e centavos com vírgula', '1.188,00', sh_money(1188));
igual('arredonda para dois dígitos',             '99,90',    sh_money(99.9));
igual('zero',                                    '0,00',     sh_money(0));
