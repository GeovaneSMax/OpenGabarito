# OpenGabarito - Inteligência Coletiva para Concursos

O **OpenGabarito** é uma plataforma disruptiva e 100% gratuita voltada para candidatos de concursos públicos no Brasil. O projeto nasceu da necessidade de democratizar o acesso a estatísticas de desempenho, combatendo os altos custos de plataformas semelhantes.

### 🎯 Missão e Funcionalidade
O site utiliza a **Inteligência Coletiva** para gerar rankings em tempo real. Os usuários inserem seus gabaritos e o sistema processa esses dados para fornecer uma visão clara da concorrência e da posição provável do candidato.

### 🧠 Tecnologia e IA
Diferente de cálculos simples, o OpenGabarito utiliza:
- **Predição de Nota de Corte (PNC)**: Processada via **Groq API** com o modelo **Llama 3.3 70B**, realizando análises estatísticas avançadas para projetar a nota necessária para aprovação.
- **Detecção Inteligente**: Algoritmos que identificam automaticamente a versão da prova do usuário por afinidade estatística.
- **Performance por Matéria**: Gráficos e indicadores de aproveitamento detalhados por disciplina.

---

# Histórico de Atualizações

**Data:** 28 de Abril de 2026

## 📱 Responsividade e UI/UX
- **Menu Mobile**: Implementação de menu hambúrguer funcional em todas as páginas principais.
- **Tabelas Otimizadas**: Redução de padding e fontes para melhor visualização em celulares.
- **Identidade Visual**: Padronização global do logo (SVG Oficial + Cores Indigo) em todas as barras de navegação e telas de acesso.
- **Rodapé Unificado**: Centralização do rodapé via função `getFooter()` (removendo duplicidades e rodapés antigos).
- **Limpeza**: Remoção de elementos desnecessários das telas de Login e Cadastro para melhor foco do usuário.

## 📊 Sistema de Matérias (Estilo De Olho No Vaga)
- **Mapeamento de Matérias**: Nova funcionalidade no Painel Admin para definir intervalos de questões por matéria (ex: 1-15 Português).
- **Desempenho Detalhado**: No ranking, agora é exibido o aproveitamento por matéria:
  - Total de pontos realizados.
  - Contador de Acertos (Azul) e Erros (Vermelho).
  - Porcentagem de acerto com alerta visual de cor (Azul para >= 50%, Vermelho para < 50%).
- **Interatividade**: Clique na sigla da matéria para ver o nome completo via Toast.

## 🤖 Inteligência Artificial e Estabilidade
- **Correção Inteligente de Versão**: Sistema agora detecta automaticamente se o usuário escolheu a versão errada da prova por afinidade estatística e corrige a nota/versão.
- **Blindagem de Consenso**: Ajuste nos pesos de votos para ignorar automaticamente "trolls" ou gabaritos muito divergentes da massa.
- **Fallback de Upload**: Correção do erro da classe `finfo` adicionando suporte automático a `getimagesize` para servidores sem a extensão habilitada.
- **Correção de Permissões**: Ajuste na pasta de `uploads` para permitir o salvamento de capas de concursos.

## ⚙️ Backend e API
- **Busca em Tempo Real**: Atualização da API de busca para retornar e exibir os logos dos concursos instantaneamente.
- **Consultas Otimizadas**: Inclusão de campos de imagem e ícones na Área do Candidato e Home.

---
*Atualizações realizadas por Gemini CLI em colaboração com o desenvolvedor.*
