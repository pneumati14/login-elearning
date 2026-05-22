/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Base path for the backend API (see .env / docker-compose.yml). */
  readonly VITE_API_URL: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
