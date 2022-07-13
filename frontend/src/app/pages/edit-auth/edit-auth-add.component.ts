import { RecordsOperation } from './../../operations/records.operations';
import { StateService } from './../../services/state.service';
import { DomainApitype } from './../../apitypes/Domain.apitype';
import { FormControl, FormBuilder, Validators } from '@angular/forms';
import { RecordApitype } from './../../apitypes/Record.apitype';
import { Component, OnInit, Input, SimpleChanges, EventEmitter, Output } from '@angular/core';

@Component({
    // tslint:disable-next-line:component-selector
    selector: '[app-edit-auth-add]',
    templateUrl: './edit-auth-add.component.html'
})
export class EditAuthAddComponent implements OnInit {

    @Input() domain: DomainApitype;

    @Output() recordAdded = new EventEmitter<RecordApitype>();

    public inputName: FormControl;
    public inputType: FormControl;
    public inputContent: FormControl;
    public inputPriority: FormControl;
    public inputTtl: FormControl;

    constructor(private fb: FormBuilder, public gs: StateService, private records: RecordsOperation) {
        this.setupFormControls();
    }

    ngOnInit(): void {
    }

    public async setupFormControls() {
        this.inputName = this.fb.control('');
        this.inputType = this.fb.control('');
        this.inputContent = this.fb.control('');
        this.inputPriority = this.fb.control('', [Validators.required, Validators.pattern(/^[0-9]+$/)]);
        this.inputTtl = this.fb.control('', [Validators.required, Validators.pattern(/^[0-9]+$/)]);
        this.resetForm();
    }

    private resetForm() {
        this.inputName.reset('');
        this.inputType.reset('A');
        this.inputContent.reset('');
        this.inputPriority.reset('0');
        this.inputTtl.reset('3600');
    }

    public fullName(): string {
        if (this.inputName.value !== '') {
            if (this.inputName.value.endsWith('.' + this.domain.name)) {
                return this.inputName.value;
            }
            return this.inputName.value + '.' + this.domain.name;
        } else {
            return this.domain.name;
        }
    }

    public async onSave(ptr) {
        const res = await this.records.create(this.domain.id, this.fullName(), this.inputType.value,
                this.inputContent.value, this.inputPriority.value, this.inputTtl.value, ptr);

        if (!res) {
            return;
        }
        res.new = true;

        this.recordAdded.emit(res);

        // this.resetForm();
    }
}
