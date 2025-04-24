<?php

namespace Database\Factories;

use App\Models\Visitor;
use App\Models\Destination;
use App\Models\DocType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Visitor>
 */
class VisitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Validação e geração de CPF válido
        $cpf = $this->generateValidCpf();
        $formattedCpf = $this->formatCpf($cpf);
        
        // Gera nomes de arquivo únicos para as imagens
        $timestamp = now()->format('YmdHis');
        $random = Str::random(5);
        $photoFilename = "photo_cpf_{$cpf}.jpg";
        $docFrontFilename = "doc_cpf_{$cpf}_front.jpg";
        $docBackFilename = "doc_cpf_{$cpf}_back.jpg";
        
        // Busca destino e tipo de documento
        $destination = Destination::where('is_active', true)->inRandomOrder()->first();
        $docType = DocType::where('type', 'CPF')->first() ?? DocType::first();
        
        // Baixa e salva as imagens de teste
        $this->downloadAndSaveImage(
            "https://thispersondoesnotexist.com?" . time() . rand(1000, 9999),
            $photoFilename
        );
        
        $this->downloadAndSaveImage(
            "https://picsum.photos/600/400?random=" . rand(1, 1000) . "&t=" . time(),
            $docFrontFilename
        );
        
        $this->downloadAndSaveImage(
            "https://picsum.photos/600/400?random=" . rand(1001, 2000) . "&t=" . time(),
            $docBackFilename
        );
        
        return [
            'name' => $this->faker->name,
            'doc' => $formattedCpf,
            'photo' => $photoFilename,
            'doc_photo_front' => $docFrontFilename,
            'doc_photo_back' => $docBackFilename,
            'phone' => $this->faker->numerify('(##) #########'),
            'destination_id' => $destination->id,
            'doc_type_id' => $docType->id,
            'other' => json_encode([]),
            'has_restrictions' => false,
        ];
    }
    
    /**
     * Baixa e salva uma imagem de teste
     */
    protected function downloadAndSaveImage(string $url, string $filename): bool
    {
        try {
            $photoDir = 'visitors-photos';
            $path = Storage::disk('private')->path($photoDir);
            
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }
            
            $fullPath = $path . '/' . $filename;
            
            $client = new \GuzzleHttp\Client();
            $response = $client->get($url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ],
            ]);
            
            file_put_contents($fullPath, $response->getBody());
            
            // Define permissões corretas
            chmod($fullPath, 0600);
            
            // Usa sudo para alterar o proprietário do arquivo para www-data
            exec("sudo chown www-data:www-data " . escapeshellarg($fullPath));
            
            return true;
        } catch (\Exception $e) {
            Log::error("Erro ao baixar imagem: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gera um CPF válido
     */
    protected function generateValidCpf(): string
    {
        // Gera os primeiros 9 dígitos aleatoriamente
        $cpf = [];
        for ($i = 0; $i < 9; $i++) {
            $cpf[] = rand(0, 9);
        }
        
        // Calcula o primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += $cpf[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $cpf[] = $remainder < 2 ? 0 : 11 - $remainder;
        
        // Calcula o segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += $cpf[$i] * (11 - $i);
        }
        $remainder = $sum % 11;
        $cpf[] = $remainder < 2 ? 0 : 11 - $remainder;
        
        // Retorna o CPF como string (apenas números)
        return implode('', $cpf);
    }
    
    /**
     * Formata um CPF como XXX.XXX.XXX-XX
     */
    protected function formatCpf(string $cpf): string
    {
        return substr($cpf, 0, 3) . '.' . 
               substr($cpf, 3, 3) . '.' . 
               substr($cpf, 6, 3) . '-' . 
               substr($cpf, 9, 2);
    }
}
