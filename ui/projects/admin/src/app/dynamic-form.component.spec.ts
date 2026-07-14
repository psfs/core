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

  it('allows an empty masked secret so the backend can preserve its stored value', () => {
    TestBed.configureTestingModule({ imports: [DynamicFormComponent] });
    const fixture = TestBed.createComponent(DynamicFormComponent);
    const schema = { name: 'demo', title: 'Demo', fields: {
      password: { name: 'password', label: 'Clave', type: 'password', value: '', required: true, preserveIfEmpty: true }
    } };
    fixture.componentInstance.schema = schema;
    fixture.componentInstance.ngOnChanges({ schema: new SimpleChange(null, schema, true) });

    expect(fixture.componentInstance.form.valid).toBe(true);
  });
});
