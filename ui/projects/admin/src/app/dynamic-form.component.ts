import { ChangeDetectionStrategy, Component, EventEmitter, Input, OnChanges, Output, SimpleChanges, signal } from '@angular/core';
import { NgFor, NgIf } from '@angular/common';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { tablerEye, tablerEyeOff, tablerTrash } from '@ng-icons/tabler-icons';
import { AdminField, AdminFormSchema } from './admin-contracts';
import { AdminTranslatePipe } from './admin-translate.pipe';

export interface DynamicFormSubmission {
  values: Record<string, unknown>;
  extra: Record<string, unknown>;
  preserve?: string[];
}

@Component({
  selector: 'psfs-dynamic-form',
  imports: [AdminTranslatePipe, NgFor, NgIf, NgIcon, ReactiveFormsModule],
  providers: [provideIcons({ tablerEye, tablerEyeOff, tablerTrash })],
  template: `
    <form class="dynamic-form" [formGroup]="form" (ngSubmit)="submit()" novalidate>
      <div class="form-field" [class.form-field--wide]="isWide(field)" *ngFor="let field of fields">
        <div class="form-field-heading">
          <label [for]="field.name">{{ (field.label || field.name) | t }} <span *ngIf="field.required" aria-hidden="true">*</span></label>
          <button *ngIf="removable" class="field-remove" type="button" [attr.data-field-name]="field.name" [attr.aria-label]="('Remove' | t) + ' ' + ((field.label || field.name) | t)" (click)="removeField(field.name)"><ng-icon name="tablerTrash" aria-hidden="true" /><span>{{ 'Remove' | t }}</span></button>
        </div>
        <textarea *ngIf="field.type === 'textarea'" class="form-control" [id]="field.name" [formControlName]="controlName(field.name)" [attr.aria-invalid]="messages(field.name).length > 0"></textarea>
        <select *ngIf="field.type === 'select'" class="form-control" [id]="field.name" [formControlName]="controlName(field.name)" [attr.aria-invalid]="messages(field.name).length > 0">
          <option *ngFor="let option of optionsFor(field)" [value]="option.value">{{ option.label | t }}</option>
        </select>
        <input *ngIf="field.type === 'checkbox'" class="form-control form-control--checkbox" [id]="field.name" type="checkbox" [formControlName]="controlName(field.name)" [attr.aria-invalid]="messages(field.name).length > 0">
        <div *ngIf="field.type !== 'textarea' && field.type !== 'select' && field.type !== 'checkbox'" class="form-control-wrap" [class.form-control-wrap--password]="field.type === 'password'">
          <input class="form-control" [id]="field.name" [type]="inputType(field)" [formControlName]="controlName(field.name)" [attr.aria-invalid]="messages(field.name).length > 0">
          <button *ngIf="field.type === 'password'" class="password-toggle" type="button" [attr.data-password-toggle]="field.name" [attr.aria-label]="(passwordVisible(field.name) ? 'Hide password' : 'Show password') | t" [attr.aria-pressed]="passwordVisible(field.name)" (click)="togglePassword(field.name)"><ng-icon [name]="passwordVisible(field.name) ? 'tablerEyeOff' : 'tablerEye'" aria-hidden="true" /><span class="visually-hidden">{{ (passwordVisible(field.name) ? 'Hide password' : 'Show password') | t }}</span></button>
        </div>
        <small *ngIf="field.help" class="form-help">{{ field.help | t }}</small>
        <ul class="field-errors" *ngIf="messages(field.name).length" [attr.aria-label]="('Field errors for {{field}}' | t: { field: ((field.label || field.name) | t) })">
          <li *ngFor="let message of messages(field.name)">{{ message }}</li>
        </ul>
      </div>
      <div class="form-actions">
        <button class="button button--primary" type="submit" [disabled]="disabled">{{ submitLabel | t }}</button>
      </div>
    </form>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class DynamicFormComponent implements OnChanges {
  @Input({ required: true }) schema!: AdminFormSchema;
  @Input() errors: Record<string, string[]> = {};
  @Input() disabled = false;
  @Input() removable = false;
  @Input() submitLabel = 'Guardar configuración';
  @Output() submitted = new EventEmitter<DynamicFormSubmission>();

  form = new FormGroup({});
  fields: AdminField[] = [];
  private readonly controls = new Map<string, string>();
  private readonly options = new Map<string, Array<{ label: string; value: string }>>();
  private readonly visiblePasswords = signal<Set<string>>(new Set());

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['schema']) {
      this.fields = Object.values(this.schema?.fields ?? {});
      const controls: Record<string, FormControl> = {};
      this.controls.clear();
      this.options.clear();
      for (const [index, field] of this.fields.entries()) {
        const controlName = `field_${index}`;
        this.controls.set(field.name, controlName);
        controls[controlName] = new FormControl(field.type === 'checkbox' ? Boolean(field.value) : (field.value ?? ''), field.required && !field.preserveIfEmpty ? Validators.required : []);
      }
      this.form = new FormGroup(controls);
    }

    if (changes['errors']) {
      for (const [name, messages] of Object.entries(this.errors)) {
        const control = this.form.get(this.controlName(name));
        if (control) {
          control.setErrors({ ...(control.errors ?? {}), server: messages });
        }
      }
    }
  }

  inputType(field: AdminField): string {
    return field.type === 'password' && !this.passwordVisible(field.name) ? 'password' : 'text';
  }

  passwordVisible(fieldName: string): boolean { return this.visiblePasswords().has(fieldName); }

  togglePassword(fieldName: string): void {
    this.visiblePasswords.update((visible) => {
      const next = new Set(visible);
      if (next.has(fieldName)) next.delete(fieldName);
      else next.add(fieldName);
      return next;
    });
  }

  removeField(fieldName: string): void {
    this.fields = this.fields.filter((field) => field.name !== fieldName);
  }

  isWide(field: AdminField): boolean { return field.type === 'textarea' || Boolean(field.help) || field.name.length > 24; }

  controlName(fieldName: string): string {
    return this.controls.get(fieldName) ?? fieldName;
  }

  optionsFor(field: AdminField): Array<{ label: string; value: string }> {
    const cached = this.options.get(field.name);
    if (cached) {
      return cached;
    }

    const options = Array.isArray(field.options)
      ? field.options
      : Object.entries(field.options ?? {}).map(([value, label]) => ({ value, label }));
    this.options.set(field.name, options);
    return options;
  }

  messages(name: string): string[] {
    const control = this.form.get(this.controlName(name));
    return this.errors[name] ?? (control?.getError('server') as string[] | undefined) ?? [];
  }

  submit(): void {
    this.form.markAllAsTouched();
    const rawValues = this.form.getRawValue() as Record<string, unknown>;
    const values = Object.fromEntries(this.fields.map((field) => [field.name, rawValues[this.controlName(field.name)]]));
    const preserve = this.fields
      .filter((field) => field.type === 'password' && field.preserveIfEmpty && !this.form.get(this.controlName(field.name))?.dirty && values[field.name] === '')
      .map((field) => field.name);
    this.submitted.emit({ values, extra: {}, preserve });
  }
}
