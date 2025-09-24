<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Discador.net</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Configuração customizada do Tailwind para usar nossa paleta de cores
        // É crucial que isso esteja no <head> para ser carregado antes do body
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'fundo-principal': '#1a1a2e',
                        'card-fundo': '#2a2a45',
                        'azul-principal': '#1a2533',
                        'azul-acento': '#30a8f0',
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Garante que a fonte seja aplicada */
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-fundo-principal flex items-center justify-center min-h-screen">

    <main class="w-full max-w-md mx-auto p-6 md:p-8">
        
        <div class="text-center mb-8">
            <img src="../assets/images/logo.png" alt="Logo Discador.net" class="mx-auto h-20 w-auto">
        </div>

        <div class="bg-card-fundo rounded-lg shadow-xl p-8">
            
            <?php
                // Inicia a sessão para poder acessar a variável de erro
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                // Verifica se existe uma mensagem de erro na sessão e a exibe
                if (isset($_SESSION['login_error'])) {
                    // A caixa de erro agora usa as cores corretas
                    echo '<div class="bg-red-500 text-white text-sm font-bold p-3 rounded-md mb-6 text-center">';
                    echo $_SESSION['login_error'];
                    echo '</div>';
                    // Remove a mensagem da sessão para que não apareça novamente
                    unset($_SESSION['login_error']);
                } else {
                    echo '<h1 class="text-white text-2xl font-bold text-center mb-2">Bem-vindo de volta!</h1>';
                    echo '<p class="text-gray-300 text-center mb-8">Acesse sua conta para continuar.</p>';
                }
            ?>

            <form action="../src/controllers/login_controller.php" method="POST">
                <div class="mb-4">
                    <label for="email" class="block text-gray-300 text-sm font-medium mb-2">E-mail</label>
                    <input type="email" id="email" name="email"
                           class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal placeholder-gray-500"
                           placeholder="seuemail@exemplo.com" required>
                </div>

                <div class="mb-6">
                    <label for="senha" class="block text-gray-300 text-sm font-medium mb-2">Senha</label>
                    <input type="password" id="senha" name="senha"
                           class="w-full px-4 py-3 bg-fundo-principal text-white border border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-roxo-principal placeholder-gray-500"
                           placeholder="********" required>
                </div>

                <div class="flex items-center justify-end mb-6">
                    <a href="client/esqueci_senha.php" class="text-sm text-azul-acento hover:underline">Esqueceu a senha?</a>
                </div>

                <div class="mb-4">
                    <button type="submit"
                            class="w-full bg-azul-acento text-white font-bold py-3 px-4 rounded-md hover:bg-purple-600 transition duration-300">
                        Acessar
                    </button>
                </div>
                
                <div>
                     <button type="button" onclick="location.href='client/cadastro.php'"
                            class="w-full bg-azul-principal text-white font-bold py-3 px-4 rounded-md hover:bg-orange-600 transition duration-300">
                        Me cadastrar
                    </button>
                </div>
            </form>
        </div>
        
    </main>

</body>
</html>