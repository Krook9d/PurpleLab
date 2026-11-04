FROM node:22-slim AS build

WORKDIR /app

COPY package.json package-lock.json ./
# --legacy-peer-deps: react-simple-maps' peerDependencies range (^16-18)
# predates React 19, but it works fine in practice (plain SVG rendering,
# no removed React 19 API) — verified live, not a blind override.
RUN npm ci --legacy-peer-deps

COPY . .
# /api by default: the container's nginx (see nginx.conf) proxies this
# prefix to the backend service (prefix stripped before relaying), so the
# browser calls relative paths on the same origin as the frontend, with no
# collision against React routes sharing a name with a backend router
# (e.g. /sigma page vs /sigma router — see Milestone 5). Also useful for
# remote access (VSCode Remote-SSH): no need to forward a second port to
# reach the API.
ARG VITE_API_BASE_URL="/api"
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL
RUN npm run build

FROM nginx:1.27-alpine

COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
