<?php 
session_start();

require_once __DIR__ . '/../../config/config.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Discador.net</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'fundo-principal': '#1a1a2e', 'card-fundo': '#2a2a45',
                        'roxo-principal': '#7e57c2', 'laranja-acento': '#ff7043',
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style> 
        body { font-family: 'Inter', sans-serif; }
        
        /* Máscara CPF */
        .cpf-mask {
            font-family: monospace;
        }
    </style>
</head>
<body class="bg-fundo-principal flex items-center justify-center min-h-screen">

    <main class="w-full max-w-md mx-auto p-6 md:p-8">
        <div class="text-center mb-8">
            <img src="../../assets/images/logo.png" alt="Logo Discador.net" class="mx-auto h-20 w-auto">
        </div>

        <div class="bg-card-fundo rounded-lg shadow-xl p-8">
            <h1 class="text-white text-2xl font-bold text-center mb-2">Crie sua Conta</h1>
            <p class="text-gray-300 text-center mb-8">É rápido e fácil.</p>

            <?php
            if (isset($_SESSION['error_message'])) {
                echo '<div class="bg-red-500 text-white p-3 rounded-md mb-4 text-sm">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
                unset($_SESSION['error_message']);
            }
            ?>

            <form action="../../src/controllers/cadastro_controller.php" method="POST">
                <div class="mb-4">
                    <label for="nome" class="block text-gray-300 text-sm font-medium mb-2">Nome Completo</label>
                    <input type="text" id="nome" name="nome" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" required>
                </div>
                
                <div class="mb-4">
                    <label for="cpf" class="block text-gray-300 text-sm font-medium mb-2">CPF</label>
                    <input type="text" id="cpf" name="cpf" class="cpf-mask w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" 
                           placeholder="000.000.000-00" maxlength="14" required>
                    <p class="text-xs text-gray-500 mt-1">Necessário para emissão de cobranças</p>
                </div>
                
                <div class="mb-4">
                    <label for="email" class="block text-gray-300 text-sm font-medium mb-2">E-mail</label>
                    <input type="email" id="email" name="email" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" required>
                </div>
                
                <div class="mb-4">
                    <label for="telefone" class="block text-gray-300 text-sm font-medium mb-2">Telefone (Opcional)</label>
                    <input type="text" id="telefone" name="telefone" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" 
                           placeholder="(11) 99999-9999">
                </div>
                
                <div class="mb-4">
                    <label for="senha" class="block text-gray-300 text-sm font-medium mb-2">Senha</label>
                    <input type="password" id="senha" name="senha" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" required>
                </div>
                <div class="mb-6">
                    <label for="senha_confirm" class="block text-gray-300 text-sm font-medium mb-2">Confirmar Senha</label>
                    <input type="password" id="senha_confirm" name="senha_confirm" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" required>
                </div>
                <div class="mb-4">
                    <button type="submit" class="w-full bg-roxo-principal text-white font-bold py-2 px-4 rounded-md hover:bg-purple-600 transition duration-300">
                        Cadastrar
                    </button>
                </div>
                <div class="text-center">
                    <a href="<?php echo BASE_URL; ?>views/login.php" class="text-sm text-roxo-principal hover:underline">Já tem uma conta? Faça login</a>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Máscara para CPF
        document.getElementById('cpf').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });

        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
            e.target.value = value;
        });
    </script>

</body>
</html>