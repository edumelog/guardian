<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
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
    
    <!-- Seção de Visitas -->
    <h2>Visitas ({{ count($results) }})</h2>
    
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
        @if(count($results) > 10)
            <div class="page-break"></div>
        @endif
        
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
                            <td>{{ $occurrence['title'] }}</td>
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