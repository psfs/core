import { TestBed } from '@angular/core/testing';
import { SimpleChange } from '@angular/core';
import { describe, expect, it } from 'vitest';
import { DynamicFormComponent } from './dynamic-form.component';

describe('DynamicFormComponent', () => {
  it('renders ordinary fields in the shared multi-column form grid', () => {
    TestBed.configureTestingModule({ imports: [DynamicFormComponent] });
    const fixture = TestBed.createComponent(DynamicFormComponent);
    const schema = { name: 'demo', title: 'Demo', fields: {
      first: { name: 'first', label: 'Primero', type: 'text' },
      second: { name: 'second', label: 'Segundo', type: 'text' },
      note: { name: 'note', label: 'Nota', type: 'textarea' },
    } };
    fixture.componentInstance.schema = schema;
    fixture.componentInstance.ngOnChanges({ schema: new SimpleChange(null, schema, true) });
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('form.dynamic-form')).not.toBeNull();
    expect(fixture.nativeElement.querySelectorAll('.form-field')).toHaveLength(3);
    expect(fixture.nativeElement.querySelector('.form-field--wide textarea')).not.toBeNull();
  });

  it('marks an untouched masked secret for explicit preservation', () => {
    TestBed.configureTestingModule({ imports: [DynamicFormComponent] });
    const fixture = TestBed.createComponent(DynamicFormComponent);
    const submissions: unknown[] = [];
    fixture.componentInstance.submitted.subscribe((submission) => submissions.push(submission));
    const schema = { name: 'demo', title: 'Demo', fields: {
      password: { name: 'password', label: 'Clave', type: 'password', value: '', required: true, preserveIfEmpty: true }
    } };
    fixture.componentInstance.schema = schema;
    fixture.componentInstance.ngOnChanges({ schema: new SimpleChange(null, schema, true) });

    expect(fixture.componentInstance.form.valid).toBe(true);
    fixture.componentInstance.submit();
    expect(submissions).toEqual([{ values: { password: '' }, extra: {}, preserve: ['password'] }]);
  });

  it('allows the user to show and hide a password value', () => {
    TestBed.configureTestingModule({ imports: [DynamicFormComponent] });
    const fixture = TestBed.createComponent(DynamicFormComponent);
    const schema = { name: 'demo', title: 'Demo', fields: {
      password: { name: 'password', label: 'Clave', type: 'password', value: '', required: false }
    } };
    fixture.componentInstance.schema = schema;
    fixture.componentInstance.ngOnChanges({ schema: new SimpleChange(null, schema, true) });
    fixture.detectChanges();

    const input = fixture.nativeElement.querySelector('#password') as HTMLInputElement;
    const toggle = fixture.nativeElement.querySelector('[data-password-toggle="password"]') as HTMLButtonElement;
    expect(input.type).toBe('password');
    expect(toggle).not.toBeNull();
    if (!toggle) return;
    toggle.click();
    fixture.detectChanges();
    expect(input.type).toBe('text');
    toggle.click();
    fixture.detectChanges();
    expect(input.type).toBe('password');
  });

  it('omits a removed field but keeps an explicitly empty field in the save payload', () => {
    TestBed.configureTestingModule({ imports: [DynamicFormComponent] });
    const fixture = TestBed.createComponent(DynamicFormComponent);
    const submissions: unknown[] = [];
    fixture.componentInstance.submitted.subscribe((submission) => submissions.push(submission));
    const schema = { name: 'demo', title: 'Demo', fields: {
      remove: { name: 'remove', label: 'Eliminar', type: 'text', value: 'old' },
      keep: { name: 'keep', label: 'Conservar', type: 'text', value: '' }
    } };
    fixture.componentInstance.schema = schema;
    Object.assign(fixture.componentInstance, { removable: true });
    fixture.componentInstance.ngOnChanges({ schema: new SimpleChange(null, schema, true) });
    fixture.detectChanges();

    const remove = fixture.nativeElement.querySelector('[data-field-name="remove"]') as HTMLButtonElement;
    expect(remove).not.toBeNull();
    if (!remove) return;
    remove.click();
    fixture.detectChanges();
    fixture.componentInstance.submit();

    expect(submissions).toEqual([{ values: { keep: '' }, extra: {}, preserve: [] }]);
  });
});
