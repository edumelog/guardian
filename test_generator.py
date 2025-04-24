#!/usr/bin/env python3
import os
import sys
import random
import requests
import subprocess
import time
import json
from datetime import datetime

# Configurações
PHOTO_DIR = 'storage/app/private/visitors-photos'

# Lista vazia, será preenchida dinamicamente
DEST_IDS = []

def validate_cpf(cpf):
    """Verifica se um CPF é válido."""
    # Remove caracteres não numéricos
    cpf = ''.join(filter(str.isdigit, cpf))
    
    # Verifica se o CPF tem 11 dígitos
    if len(cpf) != 11:
        return False
    
    # Verifica se todos os dígitos são iguais
    if len(set(cpf)) == 1:
        return False
    
    # Calcula o primeiro dígito verificador
    soma = 0
    for i in range(9):
        soma += int(cpf[i]) * (10 - i)
    resto = soma % 11
    digito1 = 0 if resto < 2 else 11 - resto
    
    # Verifica o primeiro dígito verificador
    if digito1 != int(cpf[9]):
        return False
    
    # Calcula o segundo dígito verificador
    soma = 0
    for i in range(10):
        soma += int(cpf[i]) * (11 - i)
    resto = soma % 11
    digito2 = 0 if resto < 2 else 11 - resto
    
    # Verifica o segundo dígito verificador
    return digito2 == int(cpf[10])

def generate_cpf():
    """Gera um CPF válido."""
    while True:
        # Gera os primeiros 9 dígitos aleatoriamente
        cpf = [random.randint(0, 9) for _ in range(9)]
        
        # Calcula o primeiro dígito verificador
        soma = sum((10 - i) * cpf[i] for i in range(9))
        resto = soma % 11
        cpf.append(0 if resto < 2 else 11 - resto)
        
        # Calcula o segundo dígito verificador
        soma = sum((11 - i) * cpf[i] for i in range(10))
        resto = soma % 11
        cpf.append(0 if resto < 2 else 11 - resto)
        
        # Formata o CPF como string
        cpf_str = ''.join(map(str, cpf))
        
        # Verifica se o CPF é válido e retorna
        if validate_cpf(cpf_str):
            return cpf_str

def format_cpf(cpf):
    """Formata um CPF como XXX.XXX.XXX-XX."""
    return f"{cpf[:3]}.{cpf[3:6]}.{cpf[6:9]}-{cpf[9:]}"

def download_image(url, output_path):
    """Baixa uma imagem de uma URL e salva no caminho especificado."""
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        response = requests.get(url, timeout=10, headers=headers)
        response.raise_for_status()
        
        with open(output_path, 'wb') as f:
            f.write(response.content)
        
        # Define as permissões corretas para 600 (rw-------)
        subprocess.run(['chmod', '600', output_path])
        
        # Tenta definir o proprietário para www-data, se falhar, continua assim mesmo
        try:
            subprocess.run(['sudo', 'chown', 'www-data:www-data', output_path], 
                          check=False, stderr=subprocess.DEVNULL)
        except Exception:
            print(f"Aviso: Não foi possível alterar o proprietário do arquivo {output_path}")
        
        return True
    except Exception as e:
        print(f"Erro ao baixar imagem de {url}: {e}")
        return False

def download_random_person(output_path):
    """Baixa uma imagem aleatória de pessoa do ThisPersonDoesNotExist."""
    url = f"https://thispersondoesnotexist.com?{int(time.time() * 1000)}"
    return download_image(url, output_path)

def download_random_document(output_path, seed):
    """Baixa uma imagem aleatória para simular um documento."""
    url = f"https://picsum.photos/600/400?random={seed}&t={int(time.time() * 1000)}"
    return download_image(url, output_path)

def generate_test_visitor(cpf):
    """Gera imagens para um visitante de teste."""
    # Define os caminhos dos arquivos
    photo_path = os.path.join(PHOTO_DIR, f"photo_cpf_{cpf}.jpg")
    doc_front_path = os.path.join(PHOTO_DIR, f"doc_cpf_{cpf}_front.jpg")
    doc_back_path = os.path.join(PHOTO_DIR, f"doc_cpf_{cpf}_back.jpg")
    
    # Baixa as imagens
    print(f"Baixando foto de perfil de ThisPersonDoesNotExist.com...")
    photo_success = download_random_person(photo_path)
    
    print(f"Baixando foto da frente do documento...")
    front_seed = random.randint(1, 1000)
    doc_front_success = download_random_document(doc_front_path, front_seed)
    
    print(f"Baixando foto do verso do documento...")
    back_seed = random.randint(1001, 2000)
    doc_back_success = download_random_document(doc_back_path, back_seed)
    
    # Aguarda um pouco entre os downloads para evitar problemas
    time.sleep(1)
    
    if not (photo_success and doc_front_success and doc_back_success):
        print(f"Falha ao baixar imagens para o CPF {cpf}")
        return None
    
    return {
        'cpf': cpf,
        'photo': os.path.basename(photo_path),
        'doc_front': os.path.basename(doc_front_path),
        'doc_back': os.path.basename(doc_back_path)
    }

def create_visitor_in_db(visitor_data):
    """Cria um visitante no banco de dados usando Laravel Tinker."""
    # Gera um nome aleatório
    first_names = ["João", "Maria", "José", "Ana", "Pedro", "Juliana", "Carlos", "Fernanda", "Paulo", "Luciana", 
                 "Lucas", "Mariana", "Roberto", "Camila", "Marcelo", "Patrícia", "Daniel", "Cristina", "Rafael", "Amanda"]
    last_names = ["Silva", "Santos", "Oliveira", "Souza", "Lima", "Pereira", "Costa", "Ferreira", "Rodrigues", "Almeida", 
                "Nascimento", "Carvalho", "Araújo", "Ribeiro", "Gomes", "Martins", "Correia", "Soares", "Vieira", "Barbosa"]
    
    name = f"{random.choice(first_names)} {random.choice(last_names)}"
    dest_id = random.choice(DEST_IDS)
    cpf = visitor_data['cpf']
    
    # Formata os nomes de arquivo para salvar no banco
    photo = visitor_data['photo']
    doc_front = visitor_data['doc_front']
    doc_back = visitor_data['doc_back']
    
    # Cria o visitante usando o Tinker
    tinker_command = f"""
    // Simular uma requisição autenticada com um usuário
    Auth::loginUsingId(1);
    
    $visitor = new \\App\\Models\\Visitor([
        'name' => '{name}',
        'doc' => '{format_cpf(cpf)}',
        'photo' => '{photo}',
        'doc_photo_front' => '{doc_front}',
        'doc_photo_back' => '{doc_back}',
        'destination_id' => {dest_id},
        'doc_type_id' => 1  // CPF
    ]);
    $visitor->save();
    echo "Visitante criado com sucesso: ID: " . $visitor->id . ", Nome: {name}, CPF: {format_cpf(cpf)}";
    """
    
    # Executa o comando no Tinker
    result = subprocess.run(['php', 'artisan', 'tinker', '--execute=' + tinker_command], 
                         capture_output=True, text=True)
    
    if "Visitante criado com sucesso" in result.stdout:
        print(f"✅ {result.stdout.strip()}")
        return True
    else:
        print(f"❌ Erro ao criar visitante: {result.stdout} {result.stderr}")
        return False

def obter_destinos_ativos():
    """Obtém a lista de IDs de destinos ativos no sistema"""
    global DEST_IDS
    
    print("Obtendo lista de destinos ativos...")
    
    # Consulta todos os destinos ativos usando Tinker
    tinker_command = """
    echo json_encode(
        \\App\\Models\\Destination::where('is_active', true)
            ->pluck('id')
            ->toArray()
    );
    """
    
    # Executa o comando no Tinker
    result = subprocess.run(['php', 'artisan', 'tinker', '--execute=' + tinker_command], 
                          capture_output=True, text=True)
    
    try:
        # Extrai o JSON da saída do Tinker
        output = result.stdout.strip()
        json_start = output.find('[')
        json_end = output.rfind(']') + 1
        
        if json_start >= 0 and json_end > json_start:
            json_data = output[json_start:json_end]
            DEST_IDS = json.loads(json_data)
            print(f"✅ {len(DEST_IDS)} destinos ativos encontrados.")
            return True
        else:
            print("⚠️ Formato de saída inesperado ao buscar destinos:")
            print(output)
            return False
    except Exception as e:
        print(f"❌ Erro ao processar destinos: {e}")
        print(f"Saída do comando: {result.stdout}")
        print(f"Erro do comando: {result.stderr}")
        return False

def main():
    # Verifica se o diretório de fotos existe
    if not os.path.exists(PHOTO_DIR):
        print(f"Diretório {PHOTO_DIR} não existe. Criando...")
        os.makedirs(PHOTO_DIR, exist_ok=True)
    
    # Obtém a lista de destinos ativos
    if not obter_destinos_ativos() or not DEST_IDS:
        print("❌ Não foi possível obter a lista de destinos ativos.")
        print("Por favor, verifique se existem destinos cadastrados e ativos no sistema.")
        return
    
    # Pergunta quantos visitantes criar
    try:
        num_visitors = int(input("Quantos visitantes deseja criar? "))
        if num_visitors <= 0:
            print("O número de visitantes deve ser maior que zero.")
            return
    except ValueError:
        print("Por favor, digite um número válido.")
        return
    
    print(f"Gerando {num_visitors} visitantes de teste...")
    
    # Gera os visitantes
    success_count = 0
    
    for i in range(num_visitors):
        print(f"\nProcessando visitante {i+1}/{num_visitors}...")
        
        # Gera um CPF válido
        cpf = generate_cpf()
        print(f"CPF gerado: {format_cpf(cpf)}")
        
        # Gera as imagens
        visitor_data = generate_test_visitor(cpf)
        
        if visitor_data:
            # Cria o visitante no banco de dados
            if create_visitor_in_db(visitor_data):
                success_count += 1
        
        # Pausa breve para evitar sobrecarga
        time.sleep(1)
    
    # Exibe o resumo
    print(f"\n✅ Criados {success_count} visitantes de teste com sucesso!")
    print(f"❌ Falha em {num_visitors - success_count} visitantes.")

if __name__ == "__main__":
    main() 