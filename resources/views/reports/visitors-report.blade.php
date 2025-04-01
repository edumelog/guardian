<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Visitantes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            border-bottom: 1px solid #ccc;
        }
        .header h2 {
            margin: 0;
            font-size: 18px;
            color: #2563eb;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 12px;
            color: #666;
        }
        .filter-info {
            margin-bottom: 15px;
            border: 1px solid #e5e7eb;
            padding: 10px;
            border-radius: 5px;
            background-color: #f9fafb;
        }
        .filter-info h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 14px;
            color: #374151;
        }
        .filter-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .filter-info table td {
            padding: 5px;
            vertical-align: top;
        }
        .filter-info table td:first-child {
            font-weight: bold;
            width: 150px;
        }
        .results {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .results th {
            background-color: #2563eb;
            color: white;
            font-weight: bold;
            text-align: left;
            padding: 8px;
            font-size: 12px;
            border: 1px solid #1e40af;
        }
        .results td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            font-size: 11px;
        }
        .results tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .status-active {
            color: #2563eb;
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
            padding-top: 5px;
            border-top: 1px solid #e5e7eb;
        }
        .page-break {
            page-break-after: always;
        }
        .warning {
            color: #d97706;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>RELATÓRIO DE VISITANTES - GUARDIAN</h2>
        <p>Gerado em: {{ $filterInfo['generated_at'] }} por {{ $filterInfo['generated_by'] }}</p>
    </div>
    
    <div class="filter-info">
        <h3>Critérios de Pesquisa</h3>
        <table>
            <tr>
                <td>Período:</td>
                <td>{{ $filterInfo['start_date'] }} às {{ $filterInfo['start_time'] }} até {{ $filterInfo['end_date'] }} às {{ $filterInfo['end_time'] }}</td>
            </tr>
            <tr>
                <td>Visitante:</td>
                <td>{{ $filterInfo['visitor_name'] }}</td>
            </tr>
            <tr>
                <td>Tipo de Documento:</td>
                <td>{{ $filterInfo['doc_type'] }}</td>
            </tr>
            <tr>
                <td>Número do Documento:</td>
                <td>{{ $filterInfo['doc'] }}</td>
            </tr>
            <tr>
                <td>Destino:</td>
                <td>{{ $filterInfo['destination'] }}</td>
            </tr>
            <tr>
                <td>Total de Registros:</td>
                <td>{{ $filterInfo['total_records'] }}</td>
            </tr>
            <tr>
                <td>Ordenado por:</td>
                <td>{{ $filterInfo['sort_field'] }} ({{ $filterInfo['sort_direction'] }})</td>
            </tr>
        </table>
    </div>
    
    @if(count($results) > 0)
        <table class="results">
            <thead>
                <tr>
                    <th>Visitante</th>
                    <th>Documento</th>
                    <th>Destino</th>
                    <th>Entrada</th>
                    <th>Saída</th>
                    <th>Duração</th>
                    <th>Operador</th>
                </tr>
            </thead>
            <tbody>
                @foreach($results as $log)
                    <tr>
                        <td>{{ $log->visitor->name ?? 'N/A' }}</td>
                        <td>{{ $log->visitor->docType->type ?? 'N/A' }}: {{ $log->visitor->doc ?? 'N/A' }}</td>
                        <td>{{ $log->destination->name ?? 'N/A' }}</td>
                        <td>{{ $log->in_date ? date('d/m/Y H:i:s', strtotime($log->in_date)) : 'N/A' }}</td>
                        <td>
                            @if($log->out_date)
                                {{ date('d/m/Y H:i:s', strtotime($log->out_date)) }}
                            @else
                                <span class="status-active">Em andamento</span>
                            @endif
                        </td>
                        <td>
                            @if(!empty($log->in_date) && !empty($log->out_date))
                                @php
                                    $inDate = new \DateTime($log->in_date);
                                    $outDate = new \DateTime($log->out_date);
                                    $interval = $inDate->diff($outDate);
                                    
                                    // Cálculo da duração em dias, horas, minutos
                                    $dias = $interval->days;
                                    $horas = $interval->h;
                                    $minutos = $interval->i;
                                    $segundos = $interval->s;
                                    
                                    // Formata a duração dependendo do tempo total
                                    if ($dias > 0) {
                                        $duracao = $dias.'d '.$horas.'h';
                                    } elseif ($horas > 0) {
                                        $duracao = $horas.'h '.$minutos.'m';
                                    } else {
                                        $duracao = $minutos.'m '.$segundos.'s';
                                    }
                                    
                                    // Calcula duração em horas para coloração condicional
                                    $horasTotal = $interval->h + ($interval->days * 24);
                                @endphp
                                
                                @if($horasTotal >= 8)
                                    <span class="warning">{{ $duracao }}</span>
                                @else
                                    {{ $duracao }}
                                @endif
                            @else
                                <span class="status-active">Em andamento</span>
                            @endif
                        </td>
                        <td>{{ $log->operator->name ?? 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="warning">Nenhum registro encontrado para os critérios especificados.</p>
    @endif
    
    <div class="footer">
        <p>Sistema Guardian - Relatório de Visitantes - Página 1</p>
    </div>
</body>
</html> 