import { ComponentFixture, TestBed } from '@angular/core/testing';
import { describe, expect, it, vi } from 'vitest';
import { ConfirmDialogComponent } from './confirm-dialog.component';

describe('ConfirmDialogComponent', () => {
  it('emits only after the explicit confirm action and can be cancelled', () => {
    TestBed.configureTestingModule({ imports: [ConfirmDialogComponent] });
    const fixture: ComponentFixture<ConfirmDialogComponent> = TestBed.createComponent(ConfirmDialogComponent);
    fixture.componentInstance.open = true;
    fixture.componentInstance.message = 'Eliminar usuario';
    const confirm = vi.fn(); const cancel = vi.fn();
    fixture.componentInstance.confirm.subscribe(confirm);
    fixture.componentInstance.cancel.subscribe(cancel);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[role="alertdialog"]')).not.toBeNull();
    fixture.nativeElement.querySelector('button.button--secondary').click();
    expect(cancel).toHaveBeenCalledTimes(1);
    expect(confirm).not.toHaveBeenCalled();
    fixture.nativeElement.querySelector('button.button--danger').click();
    expect(confirm).toHaveBeenCalledTimes(1);
  });
});
