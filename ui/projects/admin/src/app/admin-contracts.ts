export interface AdminEnvelope<T> {
  ok: boolean;
  message: string | null;
  data: T;
  errors: Record<string, string[]>;
}

export interface AdminField {
  name: string;
  label?: string;
  type?: string;
  value?: unknown;
  required?: boolean;
  options?: Array<{ label: string; value: string }> | Record<string, string>;
  help?: string;
  preserveIfEmpty?: boolean;
  rules?: Record<string, unknown>;
}

export interface AdminFormSchema {
  name: string;
  title: string;
  fields: Record<string, AdminField>;
}
