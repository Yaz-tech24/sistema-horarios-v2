FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && a2enmod rewrite headers \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

# Configuração de produção do PHP (a imagem vem sem php.ini — ou seja, com
# os valores de desenvolvimento por omissão) + o mínimo de endurecimento:
#   - não anunciar a versão do PHP nem a do Apache em cabeçalhos e páginas
#     de erro (poupa trabalho a quem procura versões com falhas conhecidas);
#   - erros só para o log, nunca para o browser;
#   - sessões só por cookie, com ID validado pelo servidor.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'expose_php = Off'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'session.use_strict_mode = 1'; \
        echo 'session.use_only_cookies = 1'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.cookie_samesite = Lax'; \
        echo 'allow_url_include = Off'; \
    } > "$PHP_INI_DIR/conf.d/zz-seguranca.ini" \
    && { \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
        echo 'TraceEnable Off'; \
    } > /etc/apache2/conf-available/zz-seguranca.conf \
    && a2enconf zz-seguranca

WORKDIR /var/www/html

# Os ficheiros ficam do root e só com leitura para o Apache (www-data): a
# aplicação nunca escreve em disco (as sessões vão para /tmp), por isso o
# servidor web não precisa — nem deve — poder alterar o próprio código.
COPY --chown=root:root . .
RUN chmod -R a+rX,go-w /var/www/html

EXPOSE 80
