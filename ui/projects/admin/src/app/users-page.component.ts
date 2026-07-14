import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { NgFor, NgIf } from '@angular/common';
import { AdminApiService } from './admin-api.service';
import { AdminEnvelope, AdminFormSchema } from './admin-contracts';
import { DynamicFormComponent, DynamicFormSubmission } from './dynamic-form.component';
import { ConfirmDialogComponent } from './confirm-dialog.component';

interface AdminUser {
  username: string;
  role: string;
  class: string;
}

interface UsersContract {
  users: AdminUser[];
  form: AdminFormSchema;
  profiles: Record<string, string>;
}

@Component({
  selector: 'psfs-users-page',
  imports: [DynamicFormComponent, ConfirmDialogComponent, NgFor, NgIf],
  template: `
    <article class="native-page page users-page" aria-live="polite">
      <header class="page-header">
        <div>
          <p class="page-eyebrow">Administración</p>
          <h1>Gestión de usuarios</h1>
          <p class="page-description">Gestiona las credenciales administrativas de PSFS sin abandonar la aplicación.</p>
        </div>
      </header>

      <p *ngIf="state() === 'loading'" class="notice notice--info">Cargando usuarios…</p>
      <section *ngIf="failure() as error" class="form-errors" aria-label="Errores de usuarios">
        <p>{{ error.message || 'No se pudo completar la operación.' }}</p>
        <ul *ngIf="generalErrors().length"><li *ngFor="let message of generalErrors()">{{ message }}</li></ul>
      </section>
      <psfs-confirm-dialog [open]="pendingDelete() !== ''" title="Eliminar usuario" [message]="'Se eliminará el usuario ' + pendingDelete() + '. Esta acción no se puede deshacer.'" confirmLabel="Eliminar" (cancel)="pendingDelete.set('')" (confirm)="confirmRemove()" />
      <p *ngIf="message()" class="notice notice--success">{{ message() }}</p>

      <section *ngIf="state() === 'success'" class="users-layout">
        <div class="panel users-list">
          <div class="panel-heading users-list__heading"><div><h2>Usuarios activos</h2><p>{{ users().length }} configurados</p></div><label class="users-filter"><span class="visually-hidden">Filtrar usuarios</span><input class="form-control" type="search" placeholder="Buscar usuario o rol" [value]="filter()" (input)="setFilter($any($event.target).value)"></label></div>
          <div class="table-container" *ngIf="filteredUsers().length; else emptyUsers">
            <table class="data-table">
              <thead><tr><th scope="col">Alias</th><th scope="col">Rol</th><th scope="col"><span class="visually-hidden">Acciones</span></th></tr></thead>
              <tbody>
                <tr *ngFor="let user of visibleUsers()">
                  <td><strong>{{ user.username }}</strong></td>
                  <td><span class="role-badge" [class]="'role-badge role-badge--' + user.class">{{ user.role }}</span></td>
                  <td class="table-actions"><button type="button" class="button button--danger button--small" [disabled]="saving()" (click)="remove(user.username)">Eliminar</button></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="pagination" *ngIf="pageCount() > 1" aria-label="Paginación de usuarios">
            <button class="button button--secondary button--small" type="button" [disabled]="page() === 0" (click)="previousPage()">Anterior</button>
            <span>Página {{ page() + 1 }} de {{ pageCount() }}</span>
            <button class="button button--secondary button--small" type="button" [disabled]="page() + 1 >= pageCount()" (click)="nextPage()">Siguiente</button>
          </div>
          <ng-template #emptyUsers><p class="empty-state">No hay usuarios configurados todavía.</p></ng-template>
        </div>

        <section class="panel users-create" *ngIf="schema() as form">
          <div class="panel-heading"><div><h2>Nuevo usuario</h2><p>Define alias, contraseña y rol.</p></div></div>
          <psfs-dynamic-form [schema]="form" [errors]="fieldErrors()" [disabled]="saving()" submitLabel="Crear usuario" (submitted)="create($event)" />
        </section>
      </section>
    </article>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class UsersPageComponent {
  private readonly api = inject(AdminApiService);
  readonly users = signal<AdminUser[]>([]);
  readonly schema = signal<AdminFormSchema | null>(null);
  readonly profiles = signal<Record<string, string>>({});
  readonly state = signal<'loading' | 'success' | 'error'>('loading');
  readonly saving = signal(false);
  readonly message = signal('');
  readonly failure = signal<AdminEnvelope<null> | null>(null);
  readonly fieldErrors = signal<Record<string, string[]>>({});
  readonly pendingDelete = signal('');
  readonly filter = signal('');
  readonly page = signal(0);
  readonly filteredUsers = computed(() => {
    const query = this.filter().trim().toLocaleLowerCase();
    if (!query) return this.users();
    return this.users().filter((user) => `${user.username} ${user.role}`.toLocaleLowerCase().includes(query));
  });
  readonly pageCount = computed(() => Math.max(1, Math.ceil(this.filteredUsers().length / 20)));
  readonly visibleUsers = computed(() => {
    const start = this.page() * 20;
    return this.filteredUsers().slice(start, start + 20);
  });

  constructor() {
    this.load();
  }

  create(submission: DynamicFormSubmission): void {
    this.saving.set(true);
    this.resetFeedback();
    this.api.post<Record<string, never>>('users', submission).subscribe({
      next: (response) => {
        this.saving.set(false);
        this.message.set(response.message ?? 'Usuario creado correctamente.');
        this.load();
      },
      error: (failure: unknown) => {
        this.saving.set(false);
        this.fail(failure, 'No se pudo crear el usuario.');
      }
    });
  }

  remove(username: string): void {
    this.pendingDelete.set(username);
  }

  setFilter(value: string): void {
    this.filter.set(value);
    this.page.set(0);
  }

  previousPage(): void { this.page.update((current) => Math.max(0, current - 1)); }

  nextPage(): void { this.page.update((current) => Math.min(this.pageCount() - 1, current + 1)); }

  confirmRemove(): void {
    const username = this.pendingDelete();
    if (!username) return;
    this.pendingDelete.set('');

    this.saving.set(true);
    this.resetFeedback();
    this.api.delete<Record<string, never>>('users', { user: username }).subscribe({
      next: (response) => {
        this.saving.set(false);
        this.message.set(response.message ?? 'Usuario eliminado correctamente.');
        this.load();
      },
      error: (failure: unknown) => {
        this.saving.set(false);
        this.fail(failure, 'No se pudo eliminar el usuario.');
      }
    });
  }

  generalErrors(): string[] {
    const failure = this.failure();
    if (!failure) return [];
    return Object.entries(failure.errors)
      .filter(([field]) => !(field in this.fieldErrors()))
      .flatMap(([, messages]) => messages);
  }

  private load(): void {
    this.api.get<UsersContract>('users').subscribe({
      next: (response) => {
        this.users.set(response.data.users);
        if (this.page() >= this.pageCount()) this.page.set(Math.max(0, this.pageCount() - 1));
        this.schema.set(response.data.form);
        this.profiles.set(response.data.profiles);
        this.state.set('success');
      },
      error: (failure: unknown) => this.fail(failure, 'No se pudo cargar la gestión de usuarios.')
    });
  }

  private resetFeedback(): void {
    this.message.set('');
    this.failure.set(null);
    this.fieldErrors.set({});
  }

  private fail(failure: unknown, fallback: string): void {
    const envelope = this.envelope(failure, fallback);
    this.failure.set(envelope);
    this.fieldErrors.set(envelope.errors);
    if (this.schema() === null) {
      this.state.set('error');
    }
  }

  private envelope(failure: unknown, fallback: string): AdminEnvelope<null> {
    if (typeof failure === 'object' && failure !== null && 'ok' in failure && 'errors' in failure) {
      return failure as AdminEnvelope<null>;
    }
    return { ok: false, message: fallback, data: null, errors: {} };
  }
}
