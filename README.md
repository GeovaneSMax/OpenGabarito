# 🎓 OpenGabarito - Inteligência Coletiva para Concursos

![OpenGabarito Logo](public_html/assets/img/og-image.png)

O **OpenGabarito** é uma plataforma open-source, disruptiva e 100% gratuita, projetada para candidatos de concursos públicos no Brasil. O projeto utiliza o poder da inteligência coletiva e IA de ponta para democratizar o acesso a estatísticas de desempenho e previsões de aprovação.

---

## 🚀 Funcionalidades Principais

- **📊 Rankings em Tempo Real**: Processamento instantâneo de gabaritos enviados pela comunidade.
- **🤖 Predição de Nota de Corte (PNC)**: Motor de análise estatística alimentado por **Groq API (Llama 3.3 70B)** e **Google Gemini**, projetando a nota necessária para a classificação.
- **🧩 Detecção Automática de Versão**: Algoritmos de afinidade estatística que identificam a versão da prova do usuário automaticamente.
- **📈 Desempenho por Matéria**: Visualização detalhada de acertos e erros segmentados por disciplina (estilo "De Olho No Vaga").
- **🛡️ Arquitetura Segura**: Estrutura profissional com separação de lógica de backend e web root público.

---

## 🛠️ Stack Tecnológica

- **Backend**: PHP 8.x
- **Frontend**: HTML5, Vanilla CSS, Tailwind CSS (Painel Admin), Chart.js
- **Banco de Dados**: MySQL / MariaDB
- **IA**: Groq Cloud (Llama 3), Google AI Studio (Gemini 1.5 Flash)
- **Servidor**: Nginx / Apache (recomendado Nginx)

---

## ⚙️ Instalação e Configuração

### Pré-requisitos
- Servidor PHP 8.0+
- MySQL 5.7+
- Extensão cURL habilitada

### Passo a Passo
1. **Clone o repositório**:
   ```bash
   git clone https://github.com/seu-usuario/OpenGabarito.git
   ```
2. **Configure o Servidor**:
   Aponte o `root` do seu servidor web (Nginx/Apache) para a pasta `public_html/`.
3. **Ambiente**:
   Copie o arquivo `.env.example` para `.env` e preencha com suas credenciais:
   ```bash
   cp .env.example .env
   ```
4. **Banco de Dados**:
   Importe o arquivo `database.sql` para o seu banco de dados MySQL.

---

## 🛡️ Segurança e Isolamento (Oracle/Produção)

Para rodar o OpenGabarito em servidores compartilhados com outros sistemas, siga estas recomendações:

### 1. Configuração do Nginx
Aponte o `root` estritamente para a pasta `public_html`. Isso impede o acesso direto à pasta `includes/` e ao arquivo `.env`.

```nginx
server {
    listen 80;
    server_name opengabarito.com.br;
    root /var/www/Opengabarito.com.br/public_html;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        # Isolamento via PHP open_basedir
        fastcgi_param PHP_VALUE "open_basedir=/var/www/Opengabarito.com.br/:/tmp/";
    }

    # Bloqueia acesso a arquivos sensíveis
    location ~ /\.(env|git|htaccess) {
        deny all;
    }
}
```

### 2. Permissões de Arquivos (Linux)
```bash
# Permissões restritivas no .env
chmod 600 .env

# Permissões de escrita apenas onde necessário
chmod 775 cache/
chmod 775 includes/uploads/
chmod 775 public_html/uploads/
```

### 3. Rate Limiting
O sistema já conta com um mecanismo de **Rate Limiting** embutido para chamadas de IA, prevenindo o esgotamento de cotas por bots.

---

## 🤝 Como Contribuir

Adoramos colaborações! Se você quer ajudar a melhorar o OpenGabarito:

1. Faça um **Fork** do projeto.
2. Crie uma **Branch** para sua feature (`git checkout -b feature/NovaFeature`).
3. Faça o **Commit** de suas mudanças (`git commit -m 'Adicionando nova funcionalidade'`).
4. Dê um **Push** na Branch (`git push origin feature/NovaFeature`).
5. Abra um **Pull Request**.

> **Nota**: Todas as contribuições serão revisadas pela equipe principal antes de serem integradas à versão oficial.

---

## 📄 Licença

Este projeto está sob a licença MIT - veja o arquivo [LICENSE](LICENSE) para detalhes.

---

## 📬 Contato

Desenvolvido com ❤️ para a comunidade de concurseiros.
- **Site**: [OpenGabarito.com.br](https://opengabarito.com.br)
- **Comunidade**: [Link do WhatsApp/Discord se houver]

---
*OpenGabarito: Tecnologia aberta e transparente: por um mundo onde o conhecimento pertence a todos.*
