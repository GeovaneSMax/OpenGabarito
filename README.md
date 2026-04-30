# 🎓 OpenGabarito — Inteligência Coletiva para Concursos

O **OpenGabarito** é uma plataforma open source, gratuita e colaborativa, criada para candidatos de concursos públicos no Brasil.
A proposta é simples e poderosa: usar inteligência coletiva + IA para gerar análises, rankings e previsões com rapidez e transparência.

> Código aberto, evolução contínua, conhecimento acessível.

---

## 🚀 Principais Funcionalidades

* 📊 **Rankings em tempo real**
  Processamento dinâmico dos gabaritos enviados pela comunidade.

* 🤖 **Predição de Nota de Corte (PNC)**
  Modelos estatísticos e IA para estimar desempenho e classificação.

* 🧩 **Detecção automática de versão**
  Identificação inteligente da prova com base em padrões de resposta.

* 📈 **Análise por disciplina**
  Visualização detalhada de acertos e erros por matéria.

* 🛡️ **Arquitetura segura e modular**
  Separação entre backend e camada pública.

---

## 🛠️ Stack Tecnológica

* **Backend:** PHP 8+
* **Frontend:** HTML5, CSS, Tailwind (admin), Chart.js
* **Banco de Dados:** MySQL / MariaDB
* **IA:** Integração com provedores externos (LLMs)
* **Servidor:** Compatível com Nginx ou Apache

---

## ⚙️ Instalação

### Pré-requisitos

* PHP 8.0+
* MySQL/MariaDB
* cURL habilitado

### Passos

```bash
git clone https://github.com/seu-usuario/OpenGabarito.git
cd OpenGabarito
cp .env.example .env
```

1. Configure seu servidor web apontando o root para a pasta pública do projeto
2. Preencha o arquivo `.env` com suas credenciais
3. Importe o banco de dados (`database.sql`)

---

## 🔐 Boas Práticas de Segurança

Para uso em produção:

* Nunca exponha o arquivo `.env`
* Utilize permissões restritivas nos arquivos sensíveis
* Garanta que apenas a pasta pública seja acessível via web
* Valide e sanitize todas as entradas de usuário
* Utilize rate limiting e proteção contra abuso

> Segurança não é opcional — é parte da arquitetura.

---

## 🤝 Contribuição

Quer ajudar a melhorar o projeto? Bora 🚀

1. Faça um fork
2. Crie uma branch (`feature/minha-feature`)
3. Commit suas alterações
4. Abra um Pull Request

Todas as contribuições passam por revisão antes de integração.

---

## 📄 Licença

Este projeto está sob a licença MIT.

---

## 📬 Contato & Comunidade

* 🌐 Plataforma: OpenGabarito.com.br
* 💬 Telegram: [https://t.me/+SRvNUFIdV9ZhMjVh](https://t.me/+SRvNUFIdV9ZhMjVh)

---

## 🌍 Filosofia

OpenGabarito não é só uma ferramenta.

É um movimento contra a centralização de informação.
É a ideia de que dados, conhecimento e oportunidades não devem ficar presos.

> Transparência constrói confiança.
> Comunidade constrói poder.
> Código aberto constrói o futuro.
> “Monopólios dependem de silêncio.
> Projetos abertos crescem com vozes.”

