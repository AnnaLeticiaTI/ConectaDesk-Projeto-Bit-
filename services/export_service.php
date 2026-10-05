<?php
declare(strict_types=1);

function export_dashboard(string $format): never
{
    if (!in_array($format, ['pdf', 'excel'], true)) {
        json_response(['error' => 'Formato de exportação inválido.'], 422);
    }

    $pdo = db();
    $data = dashboard_export_data($pdo);

    if ($format === 'excel') {
        send_dashboard_excel($data);
    }

    send_dashboard_pdf($data);
}

function export_reports(string $format): never
{
    if (!in_array($format, ['pdf', 'excel'], true)) {
        json_response(['error' => 'Formato de exportação inválido.'], 422);
    }

    $pdo = db();
    $data = reports_export_data($pdo);

    if ($format === 'excel') {
        send_reports_excel($data);
    }

    send_reports_pdf($data);
}

function dashboard_export_data(PDO $pdo): array
{
    $total = (int)$pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
    $status = [];
    foreach (['Aberto', 'Em Atendimento', 'Concluído'] as $item) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE status = ?');
        $stmt->execute([$item]);
        $status[$item] = (int)$stmt->fetchColumn();
    }

    $categories = $pdo->query(
        'SELECT c.name, COUNT(t.id) AS total
         FROM categories c LEFT JOIN tickets t ON t.category_id = c.id
         GROUP BY c.id, c.name ORDER BY total DESC, c.name'
    )->fetchAll();

    $daily = $pdo->query(
        'SELECT DATE(created_at) AS day, COUNT(*) AS total
         FROM tickets
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at) ORDER BY day'
    )->fetchAll();

    $users = $pdo->query(
        'SELECT u.name, COUNT(t.id) AS total,
                COALESCE(SUM(CASE WHEN t.status = \'Concluído\' THEN 1 ELSE 0 END), 0) AS solved
         FROM users u LEFT JOIN tickets t ON t.requester_id = u.id
         GROUP BY u.id, u.name ORDER BY total DESC, u.name'
    )->fetchAll();

    $content = $pdo->query(
        'SELECT COALESCE(SUM(opened), 0) AS opened,
                COALESCE(SUM(liked), 0) AS liked
         FROM content_recipients'
    )->fetch();

    $average = (float)$pdo->query('SELECT COALESCE(AVG(rating), 0) FROM ticket_ratings')->fetchColumn();

    return [
        'total' => $total,
        'status' => $status,
        'categories' => $categories,
        'daily' => $daily,
        'users' => $users,
        'content' => [
            'opened' => (int)($content['opened'] ?? 0),
            'liked' => (int)($content['liked'] ?? 0),
        ],
        'average_rating' => round($average, 2),
    ];
}

function reports_export_data(PDO $pdo): array
{
    $ratings = $pdo->query(
        'SELECT u.name, COUNT(r.id) AS evaluations,
                ROUND(COALESCE(AVG(r.rating), 0), 2) AS average_rating
         FROM users u LEFT JOIN ticket_ratings r ON r.user_id = u.id
         GROUP BY u.id, u.name ORDER BY u.name'
    )->fetchAll();

    $knowledge = $pdo->query(
        'SELECT c.title, COALESCE(SUM(cr.opened), 0) AS opens,
                COALESCE(SUM(cr.liked), 0) AS likes,
                (SELECT COUNT(*) FROM content_comments cc WHERE cc.content_id = c.id) AS comments
         FROM contents c LEFT JOIN content_recipients cr ON cr.content_id = c.id
         WHERE c.type = \'material\'
         GROUP BY c.id, c.title ORDER BY c.created_at DESC'
    )->fetchAll();

    $tickets = $pdo->query(
        'SELECT status, COUNT(*) AS total FROM tickets
         GROUP BY status
         ORDER BY FIELD(status, \'Aberto\', \'Em Atendimento\', \'Concluído\')'
    )->fetchAll();

    $categories = $pdo->query(
        'SELECT c.name, COUNT(t.id) AS total
         FROM categories c LEFT JOIN tickets t ON t.category_id = c.id
         GROUP BY c.id, c.name ORDER BY total DESC, c.name'
    )->fetchAll();

    $totalEvaluations = 0;
    $ratingTotal = 0.0;
    foreach ($ratings as $item) {
        $evaluations = (int)$item['evaluations'];
        $totalEvaluations += $evaluations;
        $ratingTotal += (float)$item['average_rating'] * $evaluations;
    }

    return [
        'ratings' => $ratings,
        'knowledge' => $knowledge,
        'tickets' => $tickets,
        'categories' => $categories,
        'summary' => [
            'evaluations' => $totalEvaluations,
            'average' => $totalEvaluations ? round($ratingTotal / $totalEvaluations, 2) : 0,
            'materials' => count($knowledge),
            'status' => count($tickets),
        ],
    ];
}

function export_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function export_trim(string $value, int $width): string
{
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $width, '…');
    }

    $text = iconv_substr($value, 0, $width, 'UTF-8');
    return iconv_strlen($value, 'UTF-8') > $width ? $text . '…' : $text;
}

function export_excel(string $title, string $body, string $filename): never
{
    $css = <<<CSS
    <style>
    @page { size: A3 landscape; margin: 0.35in; }
    body { margin:0; padding:28px; font-family:Arial,Helvetica,sans-serif; background:#ffffff; color:#102f45; }
    .sheet { width:100%; }
    .title { text-align:center; font-size:26px; font-weight:700; color:#142f54; margin:0 0 14px; }
    .subtitle { font-size:12px; color:#5d7180; margin:0 0 22px; }
    .eyebrow { font-size:11px; font-weight:700; color:#285a9f; margin-bottom:5px; }
    .kpis { display:flex; gap:8px; margin:0 0 22px; }
    .kpi { flex:1; min-height:70px; padding:14px 18px; border:1px solid #d7e2e8; border-radius:12px; background:#fff; }
    .kpi .label { font-size:10px; text-transform:uppercase; font-weight:700; color:#5e7382; }
    .kpi .value { font-size:26px; font-weight:700; color:#0f3651; margin-top:8px; }
    .layout { display:table; width:100%; table-layout:fixed; border-spacing:14px 0; margin-left:-14px; width:calc(100% + 14px); }
    .panel-cell { display:table-cell; width:50%; vertical-align:top; }
    .panel { border:1px solid #d7e2e8; border-radius:14px; padding:16px; background:#fff; min-height:250px; }
    .panel h2 { font-size:15px; margin:0 0 13px; color:#102f45; }
    table { width:100%; border-collapse:collapse; font-size:11px; }
    th { text-align:left; padding:8px; background:#e9f2f7; color:#4f6878; border:1px solid #d7e2e8; }
    td { padding:8px; border:1px solid #d7e2e8; }
    .bar { height:11px; background:#edf3f6; border-radius:7px; overflow:hidden; min-width:120px; }
    .bar i { display:block; height:100%; background:#2d7eaa; border-radius:7px; }
    .donut { width:130px; height:130px; border-radius:50%; margin:12px auto; background:conic-gradient(#e2a04a 0 45%, #5b99cc 45% 75%, #4aa878 75% 100%); position:relative; }
    .donut:after { content:''; position:absolute; inset:31px; border-radius:50%; background:#fff; }
    .chart-center { text-align:center; }
    .legend { text-align:center; font-size:10px; color:#526b7a; line-height:1.7; }
    .legend span { margin:0 7px; white-space:nowrap; }
    .dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#2e78b7; margin-right:3px; }
    .section { margin-top:22px; }
    .section-title { font-size:15px; font-weight:700; color:#102f45; margin-bottom:10px; }
    .footer { margin-top:20px; font-size:9px; color:#718391; }
    </style>
    CSS;

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'.$css.'</head><body>'.$body.'</body></html>';
    exit;
}

function dashboard_excel_html(array $data): string
{
    $total = (int)$data['total'];
    $status = $data['status'];
    $maxCategory = max(array_map(fn($item) => (int)$item['total'], $data['categories']) ?: [1]);
    $maxDaily = max(array_map(fn($item) => (int)$item['total'], $data['daily']) ?: [1]);
    $open = (int)($status['Aberto'] ?? 0);
    $work = (int)($status['Em Atendimento'] ?? 0);
    $done = (int)($status['Concluído'] ?? 0);
    $sum = max(1, $open + $work + $done);
    $openPct = round($open / $sum * 100, 1);
    $workPct = round($work / $sum * 100, 1);

    $body = '<div class="sheet"><div class="title">ConectaDesk — Dashboard</div><div class="eyebrow">DADOS</div><div class="subtitle">Uma visão analítica dos chamados, categorias, usuários e conhecimento.</div>';
    $body .= '<div class="kpis">';
    foreach ([['Total',$total,'Chamados registrados'],['Abertos',$open,'Aguardando atendimento'],['Em atendimento',$work,'Em acompanhamento'],['Concluídos',$done,'Soluções finalizadas']] as $kpi) {
        $body .= '<div class="kpi"><div class="label">'.export_escape($kpi[0]).'</div><div class="value">'.(int)$kpi[1].'</div><div class="subtitle">'.export_escape($kpi[2]).'</div></div>';
    }
    $body .= '</div>';

    $body .= '<div class="layout"><div class="panel-cell"><div class="panel"><div class="eyebrow">DISTRIBUIÇÃO</div><h2>Chamados por categoria</h2><table><tr><th>Categoria</th><th>Chamados</th><th>Distribuição</th></tr>';
    foreach ($data['categories'] as $item) {
        $value = (int)$item['total'];
        $pct = round($value / $maxCategory * 100);
        $body .= '<tr><td>'.export_escape((string)$item['name']).'</td><td>'.$value.'</td><td><div class="bar"><i style="width:'.max(2,$pct).'%"></i></div></td></tr>';
    }
    $body .= '</table></div></div>';
    $body .= '<div class="panel-cell"><div class="panel"><div class="eyebrow">SITUAÇÃO</div><h2>Status dos chamados</h2><div class="chart-center"><div class="donut" style="background:conic-gradient(#e2a04a 0 '.$openPct.'%, #5b99cc '.$openPct.'% '.($openPct+$workPct).'%, #4aa878 '.($openPct+$workPct).'% 100%);"></div><div class="legend"><span><i class="dot" style="background:#e2a04a"></i>Aberto '.$open.'</span><span><i class="dot" style="background:#5b99cc"></i>Em atendimento '.$work.'</span><span><i class="dot" style="background:#4aa878"></i>Concluído '.$done.'</span></div></div></div></div></div>';

    $body .= '<div class="layout section"><div class="panel-cell"><div class="panel"><div class="eyebrow">ÚLTIMOS 7 DIAS</div><h2>Volume de abertura</h2><table><tr><th>Dia</th><th>Aberturas</th><th>Volume</th></tr>';
    foreach ($data['daily'] as $item) {
        $value=(int)$item['total']; $pct=round($value/$maxDaily*100);
        $day=date('d/m', strtotime((string)$item['day']));
        $body .= '<tr><td>'.$day.'</td><td>'.$value.'</td><td><div class="bar"><i style="width:'.max(2,$pct).'%"></i></div></td></tr>';
    }
    $body .= '</table></div></div><div class="panel-cell"><div class="panel"><div class="eyebrow">CONHECIMENTO</div><h2>Interações</h2><table><tr><th>Indicador</th><th>Valor</th></tr><tr><td>Aberturas</td><td>'.(int)$data['content']['opened'].'</td></tr><tr><td>Curtidas</td><td>'.(int)$data['content']['liked'].'</td></tr><tr><td>Média dos chamados</td><td>'.export_escape((string)$data['average_rating']).'</td></tr></table></div></div></div>';

    $body .= '<div class="section panel"><div class="eyebrow">USUÁRIOS</div><div class="section-title">Relação de chamados por usuário</div><table><tr><th>Usuário</th><th>Chamados</th><th>Concluídos</th><th>Conclusão</th></tr>';
    foreach ($data['users'] as $user) {
        $ut=(int)$user['total']; $solved=(int)$user['solved']; $pct=$ut ? round($solved/$ut*100) : 0;
        $body .= '<tr><td>'.export_escape((string)$user['name']).'</td><td>'.$ut.'</td><td>'.$solved.'</td><td>'.$pct.'%</td></tr>';
    }
    $body .= '</table><div class="footer">ConectaDesk — exportação do Dashboard.</div></div></div>';
    return $body;
}

function reports_excel_html(array $data): string
{
    $summary = $data['summary'];
    $body = '<div class="sheet"><div class="title">ConectaDesk — Relatórios</div><div class="eyebrow">ANÁLISE</div><div class="subtitle">Resultados do atendimento e desempenho dos materiais de conhecimento.</div>';
    $body .= '<div class="kpis">';
    foreach ([
        ['Avaliações', $summary['evaluations'], 'Respostas registradas'],
        ['Média geral', number_format((float)$summary['average'], 2, '.', ''), 'Nota dos atendimentos'],
        ['Materiais', $summary['materials'], 'Conteúdos analisados'],
        ['Status', $summary['status'], 'Situações acompanhadas'],
    ] as $kpi) {
        $body .= '<div class="kpi"><div class="label">'.export_escape((string)$kpi[0]).'</div><div class="value">'.export_escape((string)$kpi[1]).'</div><div class="subtitle">'.export_escape((string)$kpi[2]).'</div></div>';
    }
    $body .= '</div>';
    $body .= '<div class="layout"><div class="panel-cell"><div class="panel"><div class="eyebrow">ATENDIMENTO</div><h2>Resultado por status</h2><table><tr><th>Status</th><th>Total</th></tr>';
    foreach ($data['tickets'] as $item) $body .= '<tr><td>'.export_escape((string)$item['status']).'</td><td>'.(int)$item['total'].'</td></tr>';
    $body .= '</table></div></div><div class="panel-cell"><div class="panel"><div class="eyebrow">AVALIAÇÕES</div><h2>Avaliações por usuário</h2><table><tr><th>Usuário</th><th>Avaliações</th><th>Média</th></tr>';
    foreach ($data['ratings'] as $item) $body .= '<tr><td>'.export_escape((string)$item['name']).'</td><td>'.(int)$item['evaluations'].'</td><td>'.export_escape((string)$item['average_rating']).'</td></tr>';
    $body .= '</table></div></div></div>';
    $body .= '<div class="section panel"><div class="eyebrow">CONHECIMENTO</div><div class="section-title">Resultados da Base de Conhecimento</div><table><tr><th>Material</th><th>Aberturas</th><th>Curtidas</th><th>Comentários</th></tr>';
    foreach ($data['knowledge'] as $item) $body .= '<tr><td>'.export_escape((string)$item['title']).'</td><td>'.(int)$item['opens'].'</td><td>'.(int)$item['likes'].'</td><td>'.(int)$item['comments'].'</td></tr>';
    $body .= '</table><div class="footer">ConectaDesk — exportação dos Relatórios.</div></div></div>';
    return $body;
}

function send_dashboard_excel(array $data): never
{
    export_excel('ConectaDesk - Dashboard', dashboard_excel_html($data), 'conectadesk-dashboard.xls');
}

function send_reports_excel(array $data): never
{
    export_excel('ConectaDesk - Relatórios', reports_excel_html($data), 'conectadesk-relatorios.xls');
}

/* PDF */
function pdf_safe(string $value): string
{
    $value = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value) ?: $value;
    return str_replace(['\\','(',')',"\r","\n"], ['\\\\','\\(','\\)',' ',' '], $value);
}

function pdf_text(string &$content, float $x, float $y, string $text, int $size = 10, bool $bold = false, array $rgb = [0.06,0.18,0.28]): void
{
    [$r,$g,$b] = $rgb;
    $font = $bold ? '/F2' : '/F1';
    $content .= sprintf("BT\n%.3f %.3f %.3f rg\n%s %d Tf\n%.2f %.2f Td\n(%s) Tj\nET\n", $r,$g,$b,$font,$size,$x,$y,pdf_safe($text));
}

function pdf_fill(string &$content, float $x, float $y, float $w, float $h, array $rgb, float $radius = 0): void
{
    [$r,$g,$b] = $rgb;
    $content .= sprintf("%.3f %.3f %.3f rg\n%.2f %.2f %.2f %.2f re f\n",$r,$g,$b,$x,$y,$w,$h);
}

function pdf_stroke(string &$content, float $x, float $y, float $w, float $h, array $rgb = [0.84,0.89,0.92]): void
{
    [$r,$g,$b]=$rgb;
    $content .= sprintf("%.3f %.3f %.3f RG\n1 w\n%.2f %.2f %.2f %.2f re S\n",$r,$g,$b,$x,$y,$w,$h);
}

function pdf_circle(string &$content, float $cx, float $cy, float $r, array $rgb, bool $fill = true): void
{
    [$rr,$gg,$bb]=$rgb;
    $content .= sprintf("%.3f %.3f %.3f %s\n",$rr,$gg,$bb,$fill?'rg':'RG');
    $points=[]; $steps=48;
    for($i=0;$i<=$steps;$i++){
        $a=2*pi()*$i/$steps;
        $points[]=[$cx+$r*cos($a),$cy+$r*sin($a)];
    }
    $content .= sprintf("%.2f %.2f m\n",$points[0][0],$points[0][1]);
    for($i=1;$i<count($points);$i++) $content .= sprintf("%.2f %.2f l\n",$points[$i][0],$points[$i][1]);
    $content .= ($fill?'f':'S')."\n";
}

function pdf_slice(string &$content, float $cx, float $cy, float $r, float $start, float $end, array $rgb): void
{
    [$rr,$gg,$bb]=$rgb;
    $content .= sprintf("%.3f %.3f %.3f rg\n%.2f %.2f m\n",$rr,$gg,$bb,$cx,$cy);
    $steps=max(2,(int)ceil(abs($end-$start)/0.12));
    for($i=0;$i<=$steps;$i++){
        $a=$start+($end-$start)*$i/$steps;
        $content .= sprintf("%.2f %.2f l\n",$cx+$r*cos($a),$cy+$r*sin($a));
    }
    $content .= "f\n";
}

function pdf_document(array $pages, float $width = 1190.55, float $height = 841.89): string
{
    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
    ];

    $pageIds = [];
    $nextObject = 5;

    foreach ($pages as $pageContent) {
        $contentId = $nextObject++;
        $pageId = $nextObject++;
        $pageIds[] = $pageId;

        $objects[$contentId] = "<< /Length " . strlen($pageContent) . " >>\n"
            . "stream\n"
            . $pageContent
            . "\nendstream";

        $objects[$pageId] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /ProcSet [/PDF /Text] /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
            $width,
            $height,
            $contentId
        );
    }

    $kids = implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageIds));
    $objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageIds) . ' >>';

    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $number => $object) {
        $offsets[$number] = strlen($pdf);
        $pdf .= $number . " 0 obj\n";
        $pdf .= $object . "\n";
        $pdf .= "endobj\n";
    }

    $xrefOffset = strlen($pdf);
    $objectCount = count($objects) + 1;

    $pdf .= "xref\n";
    $pdf .= "0 {$objectCount}\n";
    $pdf .= "0000000000 65535 f \n";

    for ($number = 1; $number < $objectCount; $number++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
    }

    $pdf .= "trailer\n";
    $pdf .= "<< /Size {$objectCount} /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n";
    $pdf .= "%%EOF";

    return $pdf;
}

function pdf_header(string &$c, string $title, string $eyebrow, string $subtitle): float
{
    pdf_text($c,55,795,$title,25,true,[0.08,0.18,0.33]);
    pdf_text($c,55,758,$eyebrow,11,true,[0.16,0.35,0.62]);
    pdf_text($c,55,738,$subtitle,10,false,[0.35,0.44,0.50]);
    return 708;
}

function pdf_kpi(string &$c, float $x, float $y, float $w, float $h, string $label, int|float $value, array $color): void
{
    pdf_fill($c,$x,$y,$w,$h,[1,1,1]);
    pdf_stroke($c,$x,$y,$w,$h,[0.82,0.87,0.90]);
    pdf_text($c,$x+18,$y+$h-25,strtoupper($label),10,true,[0.03,0.10,0.15]);
    pdf_text($c,$x+18,$y+28,(string)$value,25,true,[0.03,0.10,0.15]);
}

function pdf_panel_title(string &$c, float $x, float $y, float $w, string $eyebrow, string $title): void
{
    pdf_stroke($c,$x,$y,$w,255);
    pdf_text($c,$x+18,$y+228,$eyebrow,10,true,[0.16,0.35,0.62]);
    pdf_text($c,$x+18,$y+207,$title,15,true,[0.06,0.18,0.28]);
}

function pdf_bar(string &$c, float $x, float $y, float $w, float $h, float $ratio, array $color=[0.18,0.49,0.67]): void
{
    pdf_fill($c,$x,$y,$w,$h,[0.91,0.95,0.97]);
    pdf_fill($c,$x,$y,$w*max(0,min(1,$ratio)),$h,$color);
}

function send_simple_pdf(string $title, array $lines, string $filename): never
{
    $pages = [];
    $content = '';
    $width = 595.28;
    $height = 841.89;
    pdf_fill($content, 0, 0, $width, $height, [1, 1, 1]);
    pdf_text($content, 42, 785, 'ConectaDesk', 11, true, [0.08, 0.25, 0.36]);
    pdf_text($content, 42, 748, $title, 20, true);

    $y = 710;
    foreach ($lines as $line) {
        if ($y < 55) {
            $pages[] = $content;
            $content = '';
            pdf_fill($content, 0, 0, $width, $height, [1, 1, 1]);
            $y = 785;
        }

        $text = (string)$line;
        if ($text === '') {
            $y -= 14;
            continue;
        }

        $words = preg_split('/\s+/', $text) ?: [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (strlen($candidate) > 88) {
                pdf_text($content, 42, $y, $current, 10);
                $y -= 17;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            pdf_text($content, 42, $y, $current, 10);
            $y -= 17;
        }
        $y -= 5;
    }

    $pages[] = $content;
    $pdf = pdf_document($pages, $width, $height);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

function send_dashboard_pdf(array $data): never
{
    $pages=[]; $c='';
    pdf_fill($c,0,0,1190.55,841.89,[1,1,1]);
    pdf_header($c,'ConectaDesk — Dashboard','DADOS','Uma visão analítica dos chamados, categorias, usuários e conhecimento.');

    $gap=18; $x=55; $w=258; $y=618; $h=92;
    pdf_kpi($c,$x,$y,$w,$h,'Total',$data['total'],[0.93,0.69,0.18]);
    pdf_kpi($c,$x+$w+$gap,$y,$w,$h,'Abertos',$data['status']['Aberto']??0,[0.38,0.66,0.82]);
    pdf_kpi($c,$x+2*($w+$gap),$y,$w,$h,'Em atendimento',$data['status']['Em Atendimento']??0,[0.28,0.64,0.46]);
    pdf_kpi($c,$x+3*($w+$gap),$y,$w,$h,'Concluídos',$data['status']['Concluído']??0,[0.84,0.35,0.32]);

    $panelY=330; $panelW=527; $panelH=255; $left=55; $right=608;
    pdf_stroke($c,$left,$panelY,$panelW,$panelH); pdf_stroke($c,$right,$panelY,$panelW,$panelH);
    pdf_text($c,$left+18,$panelY+$panelH-25,'DISTRIBUIÇÃO',10,true,[0.16,0.35,0.62]);
    pdf_text($c,$left+18,$panelY+$panelH-47,'Chamados por categoria',15,true);
    $max=max(array_map(fn($v)=>(int)$v['total'],$data['categories']) ?: [1]); $rowY=$panelY+$panelH-78;
    foreach($data['categories'] as $item){
        $v=(int)$item['total']; pdf_text($c,$left+18,$rowY,export_trim((string)$item['name'], 28),9,false);
        pdf_bar($c,$left+190,$rowY-4,260,12,$v/$max); pdf_text($c,$left+465,$rowY,(string)$v,9,true); $rowY-=29; if($rowY<$panelY+20) break;
    }

    pdf_text($c,$right+18,$panelY+$panelH-25,'SITUAÇÃO',10,true,[0.16,0.35,0.62]);
    pdf_text($c,$right+18,$panelY+$panelH-47,'Status dos chamados',15,true);
    $cx=$right+190; $cy=$panelY+120; $r=82; $open=(int)($data['status']['Aberto']??0); $work=(int)($data['status']['Em Atendimento']??0); $done=(int)($data['status']['Concluído']??0); $sum=max(1,$open+$work+$done);
    $angle=-pi()/2; foreach([[$open,[0.89,0.63,0.29]],[$work,[0.36,0.60,0.80]],[$done,[0.29,0.66,0.47]]] as [$v,$col]){ $next=$angle+2*pi()*$v/$sum; if($v>0) pdf_slice($c,$cx,$cy,$r,$angle,$next,$col); $angle=$next; }
    pdf_circle($c,$cx,$cy,45,[1,1,1]); pdf_text($c,$cx-14,$cy-5,(string)$sum,17,true);
    $legendY=$panelY+55; foreach([['Aberto',$open,[0.89,0.63,0.29]],['Em atendimento',$work,[0.36,0.60,0.80]],['Concluído',$done,[0.29,0.66,0.47]]] as $item){ pdf_circle($c,$right+45,$legendY+3,5,$item[2]); pdf_text($c,$right+58,$legendY,(string)$item[0].'  '.$item[1],9); $legendY-=18; }

    $rowY=55; $rowH=255;
    pdf_stroke($c,$left,$rowY,$panelW,$rowH); pdf_stroke($c,$right,$rowY,$panelW,$rowH);
    pdf_text($c,$left+18,$rowY+$rowH-25,'ÚLTIMOS 7 DIAS',10,true,[0.16,0.35,0.62]);
    pdf_text($c,$left+18,$rowY+$rowH-47,'Volume de abertura',15,true);
    $daily=$data['daily']; $maxDaily=max(array_map(fn($v)=>(int)$v['total'],$daily) ?: [1]); $barX=$left+38; $base=$rowY+55; $count=max(1,count($daily)); $step=($panelW-76)/$count;
    foreach($daily as $item){$v=(int)$item['total'];$barH=120*($v/$maxDaily);pdf_fill($c,$barX,$base,26,max(8,$barH),[0.18,0.49,0.67]);pdf_text($c,$barX+7,$base+max(8,$barH)+8,(string)$v,8,true);pdf_text($c,$barX-2,$base-17,date('d/m',strtotime((string)$item['day'])),8);$barX+=$step;}
    pdf_text($c,$right+18,$rowY+$rowH-25,'CONHECIMENTO',10,true,[0.16,0.35,0.62]);
    pdf_text($c,$right+18,$rowY+$rowH-47,'Interações',15,true);
    $stats=[['Aberturas',(int)$data['content']['opened']],['Curtidas',(int)$data['content']['liked']],['Média dos chamados',(string)$data['average_rating']]]; $sx=$right+22;
    foreach($stats as [$label,$value]){pdf_fill($c,$sx,$rowY+85,145,85,[0.95,0.97,0.98]);pdf_text($c,$sx+16,$rowY+135,(string)$value,21,true);pdf_text($c,$sx+16,$rowY+113,$label,9,false,[0.35,0.44,0.50]);$sx+=165;}

    $pages[]=$c;
    {
        $c='';pdf_fill($c,0,0,1190.55,841.89,[1,1,1]);pdf_header($c,'ConectaDesk — Dashboard','USUÁRIOS','Relação de chamados por usuário.');
        pdf_stroke($c,55,70,1080,620); pdf_text($c,75,650,'Relação de chamados por usuário',15,true);
        $yy=615; foreach([['Usuário',75],['Chamados',360],['Concluídos',520],['Conclusão',680]] as [$h2,$xx]) pdf_text($c,$xx,$yy,$h2,10,true,[0.35,0.44,0.50]); $yy-=28;
        foreach($data['users'] as $user){if($yy<95){$pages[]=$c;$c='';pdf_fill($c,0,0,1190.55,841.89,[1,1,1]);$yy=760;pdf_text($c,75,$yy,'Usuário',10,true,[0.35,0.44,0.50]);pdf_text($c,360,$yy,'Chamados',10,true,[0.35,0.44,0.50]);pdf_text($c,520,$yy,'Concluídos',10,true,[0.35,0.44,0.50]);pdf_text($c,680,$yy,'Conclusão',10,true,[0.35,0.44,0.50]);$yy-=28;} $ut=(int)$user['total'];$solved=(int)$user['solved'];$pct=$ut?round($solved/$ut*100):0;pdf_text($c,75,$yy,export_trim((string)$user['name'], 35),9);pdf_text($c,360,$yy,(string)$ut,9);pdf_text($c,520,$yy,(string)$solved,9);pdf_bar($c,680,$yy-4,170,11,$pct/100);pdf_text($c,860,$yy,$pct.'%',9,true);$yy-=24;}
        $pages[]=$c;
    }
    $pdf=pdf_document($pages);
    header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="conectadesk-dashboard.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;
}

function send_reports_pdf(array $data): never
{
    $c='';pdf_fill($c,0,0,1190.55,841.89,[1,1,1]);pdf_header($c,'ConectaDesk — Relatórios','ANÁLISE','Resultados do atendimento e desempenho dos materiais de conhecimento.');
    $summary=$data['summary'];
    $x=55;$w=258;$gap=18;$y=620;$h=72;
    pdf_kpi($c,$x,$y,$w,$h,'Avaliações',$summary['evaluations'],[1,1,1]);
    pdf_kpi($c,$x+$w+$gap,$y,$w,$h,'Média geral',(float)$summary['average'],[1,1,1]);
    pdf_kpi($c,$x+2*($w+$gap),$y,$w,$h,'Materiais',$summary['materials'],[1,1,1]);
    pdf_kpi($c,$x+3*($w+$gap),$y,$w,$h,'Status',$summary['status'],[1,1,1]);

    $left=55;$right=608;$panelW=527;$panelY=340;$panelH=225;
    pdf_stroke($c,$left,$panelY,$panelW,$panelH);pdf_stroke($c,$right,$panelY,$panelW,$panelH);
    pdf_text($c,$left+18,$panelY+$panelH-25,'ATENDIMENTO',10,true,[0.16,0.35,0.62]);pdf_text($c,$left+18,$panelY+$panelH-47,'Resultado por status',15,true);
    $yy=$panelY+$panelH-82;foreach($data['tickets'] as $item){pdf_text($c,$left+22,$yy,(string)$item['status'],9);pdf_text($c,$left+400,$yy,(string)$item['total'],9,true);$yy-=27;}
    pdf_text($c,$right+18,$panelY+$panelH-25,'AVALIAÇÕES',10,true,[0.16,0.35,0.62]);pdf_text($c,$right+18,$panelY+$panelH-47,'Avaliações por usuário',15,true);
    $yy=$panelY+$panelH-82;foreach($data['ratings'] as $item){pdf_text($c,$right+22,$yy,export_trim((string)$item['name'], 28),9);pdf_text($c,$right+345,$yy,(string)$item['evaluations'],9);pdf_text($c,$right+420,$yy,(string)$item['average_rating'],9,true);$yy-=23;if($yy<$panelY+25)break;}
    $ky=70;pdf_stroke($c,55,$ky,1080,225);pdf_text($c,73,$ky+197,'CONHECIMENTO',10,true,[0.16,0.35,0.62]);pdf_text($c,73,$ky+175,'Resultados da Base de Conhecimento',15,true);
    $yy=$ky+145;foreach([['Material',73],['Aberturas',570],['Curtidas',680],['Comentários',790]] as [$h,$xx]) pdf_text($c,$xx,$yy,$h,10,true,[0.35,0.44,0.50]);$yy-=25;
    foreach($data['knowledge'] as $item){if($yy<$ky+22)break;pdf_text($c,73,$yy,export_trim((string)$item['title'], 58),9);pdf_text($c,570,$yy,(string)$item['opens'],9);pdf_text($c,680,$yy,(string)$item['likes'],9);pdf_text($c,790,$yy,(string)$item['comments'],9);$yy-=23;}
    $pdf=pdf_document([$c]);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="conectadesk-relatorios.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;
}
