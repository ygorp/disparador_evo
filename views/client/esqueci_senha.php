<?php session_start(); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Discador.net</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Configuração de cores do Tailwind CSS para usar nosso tema
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'fundo-principal': '#1a1a2e',
                        'card-fundo': '#2a2a45',
                        'roxo-principal': '#7e57c2',
                        'laranja-acento': '#ff7043',
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
    </head>
<body class="bg-fundo-principal flex items-center justify-center min-h-screen">

    <main class="w-full max-w-md mx-auto p-6 md:p-8">
        <div class="text-center mb-8">
            <img src="../../assets/images/logo.png" alt="Logo Discador.net" class="mx-auto h-20 w-auto">
        </div>

        <div class="bg-card-fundo rounded-lg shadow-xl p-8">
            <h1 class="text-white text-2xl font-bold text-center mb-2">Recuperar Senha</h1>
            <p class="text-gray-300 text-center mb-8">Insira seu e-mail para receber o link de redefinição.</p>

            <?php
            if (isset($_SESSION['success_message'])) {
                echo '<div class="bg-green-500 text-white p-3 rounded-md mb-4 text-sm">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                unset($_SESSION['success_message']);
            }
            if (isset($_SESSION['error_message'])) {
                echo '<div class="bg-red-500 text-white p-3 rounded-md mb-4 text-sm">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
                unset($_SESSION['error_message']);
            }
            ?>

            <form action="../../src/controllers/senha_controller.php" method="POST">
                <input type="hidden" name="action" value="request_reset">
                <div class="mb-6">
                    <label for="email" class="block text-gray-300 text-sm font-medium mb-2">E-mail</label>
                    <input type="email" id="email" name="email" class="w-full px-4 py-2 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal" placeholder="seuemail@exemplo.com" required>
                </div>
                <div class="mb-4">
                    <button type="submit" class="w-full bg-roxo-principal text-white font-bold py-2 px-4 rounded-md hover:bg-purple-600 transition duration-300">
                        Enviar Link de Recuperação
                    </button>
                </div>
                 <div class="text-center">
                    <a href="<?php echo BASE_URL; ?>views/login.php" class="text-sm text-roxo-principal hover:underline">Voltar para o Login</a>
                </div>
            </form>
        </div>
    </main>

</body>
</html>