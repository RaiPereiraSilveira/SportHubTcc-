<?php
/**
 * includes/pdf.php — escritor de PDF mínimo (SH-45)
 *
 * A súmula precisa sair em PDF porque é o documento que a coordenação
 * imprime, assina e arquiva; um HTML impresso pelo navegador sai com o
 * cabeçalho do site, a URL no rodapé e a paginação que o Chrome decidir.
 *
 * Este arquivo escreve o PDF diretamente. PDF é um formato de objetos
 * numerados com uma tabela de referências cruzadas no fim; para texto em
 * fontes padrão (as 14 que todo leitor de PDF já tem embutidas) não é
 * preciso muito mais que isso. Nenhuma dependência, nenhum binário externo,
 * nada para instalar no servidor da escola.
 *
 * Escopo: página A4 retrato, fontes Helvetica e Courier, texto, linhas,
 * retângulos e tabelas simples. É o que uma súmula usa.
 *
 * ── Acentuação ──────────────────────────────────────────────────────────
 * As fontes padrão do PDF trabalham com WinAnsiEncoding (equivalente ao
 * Latin-1 estendido), que cobre todo o português: á é í ó ú â ê ô ã õ ç à.
 * O sistema é UTF-8, então cada string passa por uma conversão antes de
 * entrar no arquivo. Caractere fora do Latin-1 (um emoji digitado no nome do
 * time, por exemplo) vira "?" em vez de corromper o PDF inteiro.
 */

class ShPdf
{
    /* A4 em pontos (1 pt = 1/72 pol). */
    const LARGURA = 595.28;
    const ALTURA  = 841.89;

    private $objetos = [];      // corpo de cada objeto, indexado a partir de 1
    private $paginas = [];      // ids dos objetos de página
    private $fluxo   = '';      // conteúdo da página em construção
    private $fonte   = 'F1';
    private $tamanho = 10;
    private $titulo  = 'Documento';

    /** Fontes disponíveis: nome interno => nome PostScript da fonte padrão. */
    private static $fontes = [
        'F1' => 'Helvetica',
        'F2' => 'Helvetica-Bold',
        'F3' => 'Helvetica-Oblique',
        'F4' => 'Courier',
    ];

    public function __construct($titulo = 'Documento') {
        $this->titulo = $titulo;
    }

    /* ── Conversão e escape ────────────────────────────────────────────────
       Dentro de uma string literal do PDF, "(", ")" e "\" precisam de
       contrabarra — senão o parser fecha a string no lugar errado.        */
    private function texto_pdf($s) {
        $s = (string)$s;
        $convertido = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
        if ($convertido === false) {
            $convertido = preg_replace('/[^\x20-\x7E]/', '?', $s);
        }
        return strtr($convertido, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '']);
    }

    /** Largura aproximada de um texto, para centralizar e quebrar linha.
        Helvetica tem largura variável; 0.5 do corpo é a média que serve
        para posicionar — não é composição tipográfica, é uma súmula. */
    public function largura_texto($texto, $tamanho = null) {
        $tamanho = $tamanho ?? $this->tamanho;
        $fator   = ($this->fonte === 'F4') ? 0.60 : ($this->fonte === 'F2' ? 0.54 : 0.50);
        return mb_strlen((string)$texto) * $tamanho * $fator;
    }

    /* ── Página ────────────────────────────────────────────────────────── */
    public function pagina() {
        $this->fechar_pagina();
        $this->fluxo = '';
        return $this;
    }

    private function fechar_pagina() {
        if ($this->fluxo === '') return;

        $conteudo_id = $this->objeto("<< /Length " . strlen($this->fluxo) . " >>\nstream\n"
                                   . $this->fluxo . "\nendstream");
        $this->paginas[] = ['conteudo' => $conteudo_id];
        $this->fluxo = '';
    }

    /* ── Desenho ───────────────────────────────────────────────────────── */

    /** Escolhe fonte e corpo. $nome: F1 normal, F2 negrito, F3 itálico, F4 mono. */
    public function fonte($nome, $tamanho) {
        $this->fonte   = isset(self::$fontes[$nome]) ? $nome : 'F1';
        $this->tamanho = (float)$tamanho;
        return $this;
    }

    /** Cor do texto e do preenchimento, em 0..255. */
    public function cor($r, $g, $b) {
        $this->fluxo .= sprintf("%.3f %.3f %.3f rg\n", $r / 255, $g / 255, $b / 255);
        return $this;
    }

    /** Cor do traço. */
    public function cor_traco($r, $g, $b) {
        $this->fluxo .= sprintf("%.3f %.3f %.3f RG\n", $r / 255, $g / 255, $b / 255);
        return $this;
    }

    /**
     * Escreve texto. A origem é o canto superior esquerdo (o PDF conta de
     * baixo para cima; a conversão fica aqui para não contaminar quem usa).
     */
    public function texto($x, $y, $conteudo) {
        $this->fluxo .= sprintf(
            "BT /%s %.2f Tf %.2f %.2f Td (%s) Tj ET\n",
            $this->fonte, $this->tamanho, $x, self::ALTURA - $y, $this->texto_pdf($conteudo)
        );
        return $this;
    }

    /** Texto centralizado num intervalo horizontal. */
    public function texto_centro($x1, $x2, $y, $conteudo) {
        $x = $x1 + (($x2 - $x1) - $this->largura_texto($conteudo)) / 2;
        return $this->texto(max($x1, $x), $y, $conteudo);
    }

    /** Texto alinhado à direita. */
    public function texto_direita($x, $y, $conteudo) {
        return $this->texto($x - $this->largura_texto($conteudo), $y, $conteudo);
    }

    /**
     * Parágrafo com quebra automática.
     * @return float o Y logo abaixo da última linha escrita
     */
    public function paragrafo($x, $y, $largura, $conteudo, $entrelinha = null) {
        $entrelinha = $entrelinha ?? ($this->tamanho * 1.45);
        $palavras   = preg_split('/\s+/', trim((string)$conteudo));
        $linha      = '';

        foreach ($palavras as $palavra) {
            $teste = ($linha === '') ? $palavra : $linha . ' ' . $palavra;
            if ($this->largura_texto($teste) > $largura && $linha !== '') {
                $this->texto($x, $y, $linha);
                $y += $entrelinha;
                $linha = $palavra;
            } else {
                $linha = $teste;
            }
        }
        if ($linha !== '') {
            $this->texto($x, $y, $linha);
            $y += $entrelinha;
        }
        return $y;
    }

    public function linha($x1, $y1, $x2, $y2, $espessura = 0.6) {
        $this->fluxo .= sprintf(
            "%.2f w %.2f %.2f m %.2f %.2f l S\n",
            $espessura, $x1, self::ALTURA - $y1, $x2, self::ALTURA - $y2
        );
        return $this;
    }

    /** $estilo: 'f' preenche, 'S' contorna, 'B' faz os dois. */
    public function retangulo($x, $y, $largura, $altura, $estilo = 'f') {
        $this->fluxo .= sprintf(
            "%.2f %.2f %.2f %.2f re %s\n",
            $x, self::ALTURA - $y - $altura, $largura, $altura, $estilo
        );
        return $this;
    }

    /* ── Montagem do arquivo ───────────────────────────────────────────── */

    private function objeto($corpo) {
        $this->objetos[] = $corpo;
        return count($this->objetos);      // ids começam em 1
    }

    /** Devolve o PDF completo como string. */
    public function saida() {
        $this->fechar_pagina();
        if (!$this->paginas) {
            $this->fluxo = "BT /F1 10 Tf 40 800 Td (Documento vazio) Tj ET\n";
            $this->fechar_pagina();
        }

        // As fontes: um objeto por face, todas com WinAnsiEncoding.
        $fontes_ids = [];
        foreach (self::$fontes as $apelido => $ps) {
            $fontes_ids[$apelido] = $this->objeto(
                "<< /Type /Font /Subtype /Type1 /BaseFont /$ps /Encoding /WinAnsiEncoding >>"
            );
        }
        $recursos = '<< /Font << ';
        foreach ($fontes_ids as $apelido => $id) {
            $recursos .= "/$apelido $id 0 R ";
        }
        $recursos .= '>> >>';

        // Reserva o id do catálogo de páginas para os /Parent apontarem certo.
        $paginas_id = count($this->objetos) + count($this->paginas) + 1;

        $ids_pagina = [];
        foreach ($this->paginas as $p) {
            $ids_pagina[] = $this->objeto(
                "<< /Type /Page /Parent $paginas_id 0 R "
              . sprintf("/MediaBox [0 0 %.2f %.2f] ", self::LARGURA, self::ALTURA)
              . "/Resources $recursos "
              . "/Contents {$p['conteudo']} 0 R >>"
            );
        }

        $kids = implode(' ', array_map(function ($id) { return "$id 0 R"; }, $ids_pagina));
        $this->objeto("<< /Type /Pages /Kids [$kids] /Count " . count($ids_pagina) . " >>");

        $catalogo_id = $this->objeto("<< /Type /Catalog /Pages $paginas_id 0 R >>");

        $info_id = $this->objeto(
            "<< /Title (" . $this->texto_pdf($this->titulo) . ") "
          . "/Producer (SportHub) /Creator (SportHub) "
          . "/CreationDate (D:" . date('YmdHis') . ") >>"
        );

        // Corpo, guardando o deslocamento de cada objeto para a tabela xref.
        $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($this->objetos as $i => $corpo) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $corpo . "\nendobj\n";
        }

        $inicio_xref = strlen($pdf);
        $total = count($this->objetos) + 1;
        $pdf .= "xref\n0 $total\n0000000000 65535 f \n";
        for ($i = 1; $i < $total; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size $total /Root $catalogo_id 0 R /Info $info_id 0 R >>\n"
              . "startxref\n$inicio_xref\n%%EOF\n";

        return $pdf;
    }

    /** Envia o PDF ao navegador. $inline = true abre na aba; false baixa. */
    public function enviar($nome_arquivo, $inline = true) {
        $conteudo = $this->saida();

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
                 . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $nome_arquivo) . '"');
            header('Content-Length: ' . strlen($conteudo));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('X-Content-Type-Options: nosniff');
        }
        echo $conteudo;
    }
}
