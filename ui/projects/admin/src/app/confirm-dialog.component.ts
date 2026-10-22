import { ChangeDetectionStrategy, Component, EventEmitter, Input, Output } from '@angular/core';
import { NgIf } from '@angular/common';
import { AdminTranslatePipe } from './admin-translate.pipe';

@Component({
  selector: 'psfs-confirm-dialog',
  imports: [AdminTranslatePipe, NgIf],
  template: `<div class="dialog-backdrop" *ngIf="open" role="presentation"><section class="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title"><h2 id="confirm-title">{{ title | t }}</h2><p>{{ message }}</p><div class="dialog-actions"><button class="button button--secondary" type="button" (click)="cancel.emit()">{{ 'Cancelar' | t }}</button><button class="button button--danger" type="button" (click)="confirm.emit()" autofocus>{{ confirmLabel | t }}</button></div></section></div>`,
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ConfirmDialogComponent {
  @Input() open = false; @Input() title = 'Confirmar acción'; @Input() message = ''; @Input() confirmLabel = 'Confirmar';
  @Output() confirm = new EventEmitter<void>(); @Output() cancel = new EventEmitter<void>();
}
