import { ChangeDetectionStrategy, Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { NgFor, NgIf } from '@angular/common';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { AdminField, AdminFormSchema } from './admin-contracts';

export interface DynamicFormSubmission {
  values: Record<string, unknown>;
  extra: Record<string, unknown>;
}

@Component({
  selector: 'psfs-dynamic-form',
  imports: [NgFor, NgIf, ReactiveFormsModule],
  template: `
    <form class="dynamic-form" [formGroup]="form" (ngSubmit)="submit()" novalidate>
      <div class="form-field" [class.form-field--wide]="isWide(field)" *ngFor="let field of fields">
        <label [for]="field.name">{{ field.label || field.name }} <span *ngIf="field.required" aria-hidden="true">*</span></label>
        <textarea *ngIf="field.type === 'textarea'" class="form-control" [id]="field.name" [formControlName]="controlName(field.name)" [attr.aria-invalid]="messages(field.name).length > 0"></textarea>
        <select *ngIf="field.type === 'select'" class="form-control" [id]="field.name" [formControlName]="controlName(field.name)" [attr.aria-invalid]="messages(field.name).length > 0">
          <option *ngFor="let option of optionsFor(field)" [value]="option.value">{{ option.label }}</option>
        </select>
        <input *ngIf="field.type === 'checkbox'" class="form-control form-control--checkbox" [id]="field.name" type="checkbox" [formControlName]="controlName(field.name)" [attr.aria-invalid]="messages(field.name).length > 0">
        <input *ngIf="field.type !== 'textarea' && field.type !== 'select' && field.type !== 'checkbox'" class="form-control" [id]="field.name" [type]="inputType(field)" [formControlName]="controlName(field.name)" [attr.aria-invalid]="messages(field.name).length > 0">
        <small *ngIf="field.help" class="form-help">{{ field.help }}</small>
        <ul class="field-errors" *ngIf="messages(field.name).length" [attr.aria-label]="'Errores de ' + field.label">
          <li *ngFor="let message of messages(field.name)">{{ message }}</li>
        </ul>
      </div>
      <div class="form-actions">
        <button class="button button--primary" type="submit" [disabled]="disabled">{{ submitLabel }}</button>
      </div>
    </form>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class DynamicFormComponent implements OnChanges {
  @Input({ required: true }) schema!: AdminFormSchema;
  @Input() errors: Record<string, string[]> = {};
  @Input() disabled = false;
  @Input() submitLabel = 'Guardar configuración';
  @Output() submitted = new EventEmitter<DynamicFormSubmission>();

  form = new FormGroup({});
  fields: AdminField[] = [];
  private readonly controls = new Map<string, string>();
  private readonly options = new Map<string, Array<{ label: string; value: string }>>();

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
    return field.type === 'password' ? 'password' : 'text';
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
    this.submitted.emit({ values, extra: {} });
  }
}
