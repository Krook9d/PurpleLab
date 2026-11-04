import { clearToken, getToken } from "@/lib/auth"

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? "http://127.0.0.1:8000"

export class ApiError extends Error {
  status: number
  detail: unknown

  constructor(status: number, detail: unknown) {
    super(typeof detail === "string" ? detail : `API error ${status}`)
    this.status = status
    this.detail = detail
  }
}

type RequestOptions = {
  method?: "GET" | "POST" | "DELETE" | "PUT"
  body?: unknown
  isFormData?: boolean
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const token = getToken()
  const headers: Record<string, string> = {}
  if (token) headers["Authorization"] = `Bearer ${token}`
  if (!options.isFormData && options.body !== undefined) {
    headers["Content-Type"] = "application/json"
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method: options.method ?? "GET",
    headers,
    body: options.isFormData
      ? (options.body as FormData)
      : options.body !== undefined
        ? JSON.stringify(options.body)
        : undefined,
  })

  if (response.status === 401) {
    clearToken()
    if (!window.location.pathname.startsWith("/login")) {
      window.location.href = "/login"
    }
    throw new ApiError(401, "Not authenticated")
  }

  if (!response.ok) {
    let detail: unknown = null
    try {
      detail = (await response.json()).detail
    } catch {
      // no JSON body, keep detail = null
    }
    throw new ApiError(response.status, detail)
  }

  if (response.status === 204) return undefined as T
  return response.json() as Promise<T>
}

export const api = {
  get: <T>(path: string) => request<T>(path, { method: "GET" }),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: "POST", body }),
  put: <T>(path: string, body?: unknown) => request<T>(path, { method: "PUT", body }),
  postForm: <T>(path: string, formData: FormData) =>
    request<T>(path, { method: "POST", body: formData, isFormData: true }),
  delete: <T>(path: string) => request<T>(path, { method: "DELETE" }),
}
