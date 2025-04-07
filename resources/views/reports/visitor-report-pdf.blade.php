<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .header img {
            max-width: 200px;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 18px;
            margin: 0 0 5px 0;
        }
        h2 {
            font-size: 16px;
            margin: 15px 0 10px 0;
            color: #2563eb;
            border-bottom: 1px solid #2563eb;
            padding-bottom: 5px;
        }
        h3 {
            font-size: 14px;
            margin: 15px 0 10px 0;
            color: #374151;
        }
        .filters {
            margin-bottom: 20px;
            background-color: #f9fafb;
            padding: 10px;
            border-radius: 5px;
        }
        .filter-item {
            margin-bottom: 5px;
        }
        .filter-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #2563eb;
            color: white;
            font-weight: bold;
            text-align: left;
            padding: 8px;
        }
        td {
            border-bottom: 1px solid #ddd;
            padding: 8px;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
        }
        .date {
            text-align: right;
            font-size: 10px;
            color: #666;
            margin-bottom: 10px;
        }
        .no-results {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }
        .page-break {
            page-break-after: always;
        }
        .stats-container {
            margin-bottom: 30px;
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 5px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .stats-summary {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .stats-card {
            background: white;
            border-radius: 5px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            width: 30%;
            text-align: center;
        }
        .stats-card-title {
            font-size: 14px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 5px;
        }
        .stats-card-value {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }
        .charts-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .chart-box {
            flex: 1;
            background: white;
            border-radius: 5px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .chart-title {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
            color: #374151;
        }
        .chart-canvas {
            width: 100%;
            height: 250px;
        }
        .severity-badge {
            display: inline-block;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .severity-high {
            background-color: #ef4444;
        }
        .severity-medium {
            background-color: #f59e0b;
        }
        .severity-low {
            background-color: #3b82f6;
        }
        .severity-info {
            background-color: #10b981;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .destination-bar {
            height: 30px;
            background-color: #3b82f6;
            margin-bottom: 5px;
            border-radius: 3px;
        }
        .destination-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 8px;
        }
        .severity-bar {
            height: 30px;
            margin-bottom: 5px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(public_path('images/logo-cmrj-horizontal.jpg'))) }}" alt="Logo CMRJ">
        <h1>{{ $title }}</h1>
    </div>
    
    <div class="date">Gerado em: {{ $date }}</div>
    
    <div class="filters">
        <div><strong>Filtros aplicados:</strong></div>
        @foreach($filters as $label => $value)
            <div class="filter-item">
                <span class="filter-label">{{ $label }}:</span>
                <span>{{ $value }}</span>
            </div>
        @endforeach
    </div>
    
    @if(isset($showStats) && $showStats)
    <!-- Seção de Estatísticas -->
    <h2 style="break-before: page; page-break-before: always;">Estatísticas</h2>
    
    <div class="stats-container">
        <div class="stats-summary">
            <div class="stats-card">
                <div class="stats-card-title">Total de Registros</div>
                <div class="stats-card-value">{{ $visitorStats['total_visitors'] }}</div>
            </div>
            
            <div class="stats-card">
                <div class="stats-card-title">Visitantes Únicos</div>
                <div class="stats-card-value">{{ $visitorStats['unique_visitors'] }}</div>
            </div>
            
            @if($hasOccurrences)
            <div class="stats-card">
                <div class="stats-card-title">Total de Ocorrências</div>
                <div class="stats-card-value">{{ $occurrenceStats['total_occurrences'] }}</div>
            </div>
            @endif
        </div>
        
        <div class="charts-container" style="{{ $hasOccurrences ? '' : 'flex-direction: column;' }}">
            <!-- Gráfico de Visitantes por Destino -->
            <div class="chart-box">
                <div class="chart-title">Visitantes por Destino</div>
                @if(count($visitorStats['visitors_by_destination']) > 0)
                    @php
                        // Limitar a 5 destinos para melhor visualização
                        $destinationData = array_slice($visitorStats['visitors_by_destination'], 0, 5, true);
                        $maxValue = max($destinationData);
                    @endphp
                    
                    @foreach($destinationData as $destination => $count)
                        @php
                            $percentage = ($count / $maxValue) * 100;
                        @endphp
                        <div class="destination-label">
                            <span title="{{ $destination }}">{{ Str::limit($destination, 25) }}</span>
                            <span>{{ $count }}</span>
                        </div>
                        <div class="destination-bar" style="width: {{ $percentage }}%"></div>
                    @endforeach
                    
                    @if(count($visitorStats['visitors_by_destination']) > 5)
                        <div style="text-align: center; font-style: italic; margin-top: 10px;">
                            E mais {{ count($visitorStats['visitors_by_destination']) - 5 }} destinos
                        </div>
                    @endif
                @else
                    <div class="no-results">Sem dados para exibir</div>
                @endif
            </div>
            
            <!-- Gráfico de Ocorrências por Severidade (apenas quando hasOccurrences for true) -->
            @if($hasOccurrences)
            <div class="chart-box">
                <div class="chart-title">Ocorrências por Severidade</div>
                @if($occurrenceStats['total_occurrences'] > 0)
                    @php
                        $severities = $occurrenceStats['occurrences_by_severity'];
                        $totalOccurrences = $occurrenceStats['total_occurrences'];
                        
                        $colors = [
                            'alta' => '#ef4444',    // vermelho
                            'média' => '#f59e0b',   // laranja/amarelo
                            'baixa' => '#10b981',   // verde
                            'informativa' => '#6b7280', // cinza
                        ];
                        
                        $labels = [
                            'alta' => 'Alta (Vermelho)',
                            'média' => 'Média (Amarelo)',
                            'baixa' => 'Baixa (Verde)',
                            'informativa' => 'Informativa (Cinza)',
                        ];
                    @endphp
                    
                    @foreach($severities as $severity => $count)
                        @if($count > 0)
                            @php
                                $percentage = ($count / $totalOccurrences) * 100;
                            @endphp
                            <div class="destination-label">
                                <span>
                                    <span class="severity-badge" style="background-color: {{ $colors[$severity] }}"></span>
                                    {{ $labels[$severity] }}
                                </span>
                                <span>{{ $count }} ({{ number_format($percentage, 1) }}%)</span>
                            </div>
                            <div class="severity-bar" style="width: {{ $percentage }}%; background-color: {{ $colors[$severity] }}"></div>
                        @endif
                    @endforeach
                @else
                    <div class="no-results">Sem ocorrências para exibir</div>
                @endif
            </div>
            @endif
        </div>
    </div>
    @endif
    
    <!-- Seção de Visitas -->
    <h2 style="break-before: page; page-break-before: always;">Visitas ({{ count($results) }})</h2>
    
    @if(count($results) > 0)
        <table>
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($results as $result)
                    <tr>
                        <td>{{ $result['visitor_name'] }}</td>
                        <td>{{ $result['document'] }}</td>
                        <td>{{ $result['destination'] }}</td>
                        <td>{{ $result['in_date'] }}</td>
                        <td>{{ $result['out_date'] }}</td>
                        <td>{{ $result['duration'] }}</td>
                        <td>{{ $result['operator'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="no-results">Nenhuma visita encontrada com os filtros aplicados.</div>
    @endif
    
    <!-- Seção de Ocorrências (se aplicável) -->
    @if($hasOccurrences)
        <div class="page-break"></div>
        
        <h2>Ocorrências ({{ count($occurrencesResults) }})</h2>
        
        @if(count($occurrencesResults) > 0)
            <table>
                <thead>
                    <tr>
                        @foreach($occurrencesHeaders as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($occurrencesResults as $occurrence)
                        <tr>
                            <td>{{ $occurrence['id'] }}</td>
                            <td>{{ $occurrence['description'] }}</td>
                            <td>{{ $occurrence['visitor'] }}</td>
                            <td>{{ $occurrence['destination'] }}</td>
                            <td>{{ $occurrence['datetime'] }}</td>
                            <td>{{ $occurrence['creator'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-results">Nenhuma ocorrência encontrada com os filtros aplicados.</div>
        @endif
    @endif
    
    <div class="footer">
        <div>Sistema Guardian - Relatório de Visitas e Ocorrências</div>
        <div>DTI - Diretoria de Tecnologia da Informação</div>
    </div>
</body>
</html> 