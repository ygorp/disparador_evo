</main>
    </div>

    <script>
        // Pega os elementos do botão e do menu pelos IDs que definimos
        const userMenuButton = document.getElementById('user-menu-button');
        const userMenu = document.getElementById('user-menu');

        // Adiciona um "ouvinte" de clique no botão
        userMenuButton.addEventListener('click', function(event) {
            // Impede que o clique no botão feche o menu imediatamente (ver próximo listener)
            event.stopPropagation();
            // Alterna a classe 'hidden' no menu. Se estiver visível, esconde. Se estiver escondido, mostra.
            userMenu.classList.toggle('hidden');
        });

        // Adiciona um "ouvinte" de clique na janela inteira
        window.addEventListener('click', function() {
            // Se o menu NÃO estiver escondido, esconde ele
            if (!userMenu.classList.contains('hidden')) {
                userMenu.classList.add('hidden');
            }
        });

        // Função para inserir a variável (chamada pelos botões criados dinamicamente)
        function insertVariable(variable) {
            const textarea = document.getElementById('mensagem');
            if (!textarea) return;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            const before = text.substring(0, start);
            const after = text.substring(end, text.length);
            textarea.value = `${before}{{${variable}}}${after}`;
            textarea.selectionStart = textarea.selectionEnd = start + variable.length + 4;
            textarea.focus();
        }

        // Função que é acionada pelo 'onchange' do input de arquivo
         function insertVariable(variable) {
            const textarea = document.getElementById('mensagem');
            if (!textarea) return;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            const before = text.substring(0, start);
            const after = text.substring(end, text.length);
            textarea.value = `${before}{{${variable}}}${after}`;
            textarea.selectionStart = textarea.selectionEnd = start + variable.length + 4;
            textarea.focus();
        }

        function handleFileSelection(event) {
            const file = event.target.files[0];
            const variablesWrapper = document.getElementById('variables-wrapper');
            const variablesContainer = document.getElementById('variables-container');

            variablesContainer.innerHTML = '';
            variablesWrapper.style.display = 'none';
            if (!file) return;

            const formData = new FormData();
            formData.append('lista_contatos', file);

            fetch('../../src/controllers/csv_controller.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.headers) {
                    variablesWrapper.style.display = 'block';
                    data.headers.forEach(header => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'bg-gray-600 text-white text-xs font-mono px-2 py-1 rounded-md hover:bg-roxo-principal';
                        button.innerHTML = `<span>{{${header}}}</span>`;
                        button.onclick = () => insertVariable(header);
                        variablesContainer.appendChild(button);
                    });
                } else {
                    alert('Erro ao processar o arquivo: ' + (data.message || 'Resposta inválida do servidor.'));
                }
            })
            .catch(error => console.error('Erro na requisição:', error));
        }

        document.addEventListener('DOMContentLoaded', function() {
        const campaignRows = document.querySelectorAll('[data-campaign-id]');
        
        if (campaignRows.length > 0) {
            let activeCampaignIds = Array.from(campaignRows).map(row => row.dataset.campaignId);
            let intervalId = null;

            const updateProgress = () => {
                if (activeCampaignIds.length === 0) {
                    if (intervalId) clearInterval(intervalId);
                    return;
                }

                fetch(`../../src/controllers/progress_controller.php?ids=${activeCampaignIds.join(',')}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        for (const id in result.data) {
                            const campaignData = result.data[id];
                            const progressBar = document.getElementById(`progress-bar-${id}`);
                            const statusSpan = document.getElementById(`status-${id}`);
                            const actionsCell = document.getElementById(`actions-cell-${id}`);

                            if (progressBar) progressBar.style.width = campaignData.progress + '%';
                            
                            if (statusSpan && statusSpan.innerText !== campaignData.status) {
                                statusSpan.innerText = campaignData.status;
                                // Atualiza a cor
                                statusSpan.className = 'px-3 py-1 text-sm rounded-full text-white ';
                                if (campaignData.status === 'Enviando') statusSpan.classList.add('bg-blue-500');
                                else if (campaignData.status === 'Pausada') statusSpan.classList.add('bg-yellow-600');
                                else if (campaignData.status === 'Concluída') statusSpan.classList.add('bg-verde-sucesso');
                                else if (campaignData.status === 'Agendada') statusSpan.classList.add('bg-purple-600');
                                else statusSpan.classList.add('bg-gray-500');
                            }

                            // *** LÓGICA PARA ATUALIZAR OS BOTÕES ***
                            if (actionsCell) {
                                let newButtonsHTML = '';
                                const reportUrl = `relatorio_campanha.php?id=${id}`;
                                const deleteUrl = `../../src/controllers/disparo_controller.php?action=delete_campaign&id=${id}`;

                                if (campaignData.status === 'Enviando') {
                                    const pauseUrl = `../../src/controllers/disparo_controller.php?action=update_status&id=${id}&status=Pausada`;
                                    newButtonsHTML = `<a href="${pauseUrl}" class="bg-yellow-600 text-white px-3 py-1 rounded-md text-sm">Pausar</a>`;
                                } else if (campaignData.status === 'Pausada') {
                                    const resumeUrl = `../../src/controllers/disparo_controller.php?action=update_status&id=${id}&status=Enviando`;
                                    newButtonsHTML = `<a href="${resumeUrl}" class="bg-blue-500 text-white px-3 py-1 rounded-md text-sm">Continuar</a>`;
                                }
                                
                                // Botão de relatório sempre aparece, mas ganha destaque se concluído
                                newButtonsHTML += ` <a href="${reportUrl}" class="bg-gray-600 text-white px-3 py-1 rounded-md text-sm hover:bg-gray-700">Relatório</a>`;
                                
                                // Botão de excluir
                                newButtonsHTML += ` <a href="${deleteUrl}" onclick="return confirm('ATENÇÃO: Esta ação é irreversível. Deseja continuar?');" class="bg-red-600 text-white px-3 py-1 rounded-md text-sm hover:bg-red-700">Excluir</a>`;

                                actionsCell.innerHTML = newButtonsHTML;
                            }

                            // Se a campanha for concluída, remove da lista de verificação
                            if (campaignData.status === 'Concluída' || campaignData.status === 'Falha') {
                                activeCampaignIds = activeCampaignIds.filter(campaignId => campaignId !== id);
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro ao atualizar progresso:', error);
                    clearInterval(intervalId); // Para o loop em caso de erro de rede
                });
            };
            
            // Inicia o loop de verificação
            intervalId = setInterval(updateProgress, 5000); // Atualiza a cada 5 segundos
            updateProgress(); // Roda uma vez imediatamente
        }
    });
    </script>

</body>
</html>