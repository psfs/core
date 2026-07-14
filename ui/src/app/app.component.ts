import { ChangeDetectionStrategy, Component } from '@angular/core';

@Component({
  selector: 'psfs-root',
  template: '<main>PSFS UI POC · HMR verificado</main>',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AppComponent {}
