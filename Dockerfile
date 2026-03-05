FROM php:8.2-cli

WORKDIR /app

COPY . .

RUN chmod +x start.sh

EXPOSE 8080

CMD ["./start.sh"]
