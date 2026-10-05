// frontend/src/lib/types.ts – gemeinsame Typen (spiegeln die JSON-Antworten von cms/api.php und cms/api-lovable-provider.php)

export interface AiProviderInfo {
  id: string;
  label: string;
  group: string;
  free: boolean;
  model: string;
  models: string[];
}

export type AiTask = 'text' | 'rewrite' | 'translate' | 'summarize' | 'json' | 'layout';

export interface AiStatus {
  providers: AiProviderInfo[];
  tasks: Record<AiTask, string>;
  default: string;
}

export interface AiGenerateRequest {
  provider: string;
  model?: string;
  task: AiTask;
  prompt: string;
  text?: string;
  language?: string;
  temperature?: number;
  max_tokens?: number;
}

export interface AiGenerateResult {
  text: string;
  data: unknown;
  provider: string;
  model: string;
  usage: { prompt_tokens: number | null; completion_tokens: number | null };
  latency_ms: number;
}

/** Ein Abschnitt des Homepage-Baukastens (cms/themes/elvado-baukasten/inc/layout.php). */
export interface LayoutSection {
  type: string;
  props: Record<string, unknown>;
}

export interface AiAdminProvider {
  id: string;
  label: string;
  group: string;
  note: string;
  verified: boolean;
  free: boolean;
  base_editable: boolean;
  base_url: string;
  model: string;
  models: string[];
  enabled: boolean;
  has_key: boolean;
  key_from_assistant: boolean;
}

export interface AiAdminConfig {
  rate_limit: number;
  default_provider: string;
  providers: AiAdminProvider[];
}

export interface AiLogRow {
  id: number;
  provider: string;
  model: string;
  task: string;
  user_name: string;
  status: string;
  http_code: number;
  error_message: string;
  prompt_chars: number;
  completion_chars: number;
  latency_ms: number;
  created_at: string;
}

export interface AiLogSummary {
  provider: string;
  calls: number;
  errors: number;
  tokens: number;
  avg_ms: number;
}

export interface LovableWidget {
  id: number;
  project_id: string;
  component_name: string;
  label: string;
  enabled: boolean;
  config: {
    posts: { limit: number; category: string; tag: string; search: string };
    attributes: Record<string, string>;
    script_url: string;
  };
}

export interface LovableAdminSettings {
  bridge: { script_url_template: string; script_hosts: string[]; allowed_origins: string[]; post_source: 'auto' | 'file' | 'db' };
  github: { repo: string; branch: string; project: string; include: string[]; auto_sync: boolean; token_set: boolean; secret_set: boolean };
}

export interface SyncStatus {
  last_sha: string;
  last_sync: string;
  last_message?: string;
  last_error?: { at: string; message: string };
  last_result?: { added: number; updated: number; removed: number; skipped: number };
  files: string[];
}

export interface LovableAdminState {
  settings: LovableAdminSettings;
  widgets: LovableWidget[] | null;
  sync: SyncStatus;
  webhook_url: string;
  provider_url: string;
  origin: string;
}

/** Antwort von cms/api-lovable-provider.php. */
export interface ProviderPayload {
  status: 'ok';
  generated_at: string;
  total: number;
  count: number;
  items: Array<{
    id: number;
    slug: string;
    title: string;
    excerpt: string;
    category: string;
    tags: string[];
    image_url: string;
    url: string;
    external: boolean;
    author: string;
    featured: boolean;
    published_at: string | null;
    body_html?: string;
  }>;
  widget?: { component_name: string; project_id: string; attributes: Record<string, string> };
}
