# ─────────────────────────────────────────────────────────────────────
# Vue.js frontend — multi-stage build
#
#   target: dev   → Vite dev server with HMR.
#                   Selected by docker-compose.override.yml (local dev).
#   target: prod  → static production bundle served by nginx.
#                   The default stage; selected by the production overlay.
#
# Both stages listen on 5173 so the Caddy reverse proxy config is shared.
# ─────────────────────────────────────────────────────────────────────

# ── Shared base: install dependencies, bring in the source ───────────
FROM node:22-alpine AS base
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .

# ── Dev: Vite dev server (source is bind-mounted over /app in compose) ─
FROM base AS dev
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]

# ── Build: compile the optimized production bundle into /app/dist ─────
FROM base AS build
ARG VITE_API_URL=/api
ENV VITE_API_URL=${VITE_API_URL}
RUN npm run build

# ── Prod: serve the static bundle with nginx ─────────────────────────
# The SPA uses HTML5 history routing, so unknown paths fall back to
# index.html instead of returning 404.
FROM nginx:alpine AS prod
COPY --from=build /app/dist /usr/share/nginx/html
RUN printf 'server {\n\
    listen 5173;\n\
    server_name _;\n\
    root /usr/share/nginx/html;\n\
    index index.html;\n\
\n\
    location / {\n\
        try_files $uri $uri/ /index.html;\n\
    }\n\
}\n' > /etc/nginx/conf.d/default.conf
EXPOSE 5173
