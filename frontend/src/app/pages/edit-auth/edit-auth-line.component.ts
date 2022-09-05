import { Router, ActivationEnd, ActivatedRoute } from '@angular/router';
import { ModalOptionsDatatype } from './../../datatypes/modal-options.datatype';
import { ModalService } from './../../services/modal.service';
import { RecordsOperation } from './../../operations/records.operations';
import { StateService } from './../../services/state.service';
import { DomainApitype } from './../../apitypes/Domain.apitype';
import { FormControl, FormBuilder, Validators } from '@angular/forms';
import { RecordApitype } from './../../apitypes/Record.apitype';
import { Component, OnInit, Input, OnChanges, SimpleChanges, EventEmitter, Output } from '@angular/core';

@Component({
    // tslint:disable-next-line:component-selector
    selector: '[app-edit-auth-line]',
    templateUrl: './edit-auth-line.component.html'
})
export class EditAuthLineComponent implements OnInit, OnChanges {

    @Input() entry: RecordApitype;
    @Input() domain: DomainApitype;

    @Output() recordUpdated = new EventEmitter<void>();
    @Output() recordDeleted = new EventEmitter<number>();

    public editMode = false;

    public inputName: FormControl;
    public inputType: FormControl;
    public inputContent: FormControl;
    public inputPriority: FormControl;
    public inputTtl: FormControl;

    constructor(private fb: FormBuilder, public gs: StateService, private records: RecordsOperation,
        private modal: ModalService, private router: Router, private route: ActivatedRoute) {
        this.setupFormControls();
    }

    ngOnInit(): void {
    }

    ngOnChanges(changes: SimpleChanges): void {
        this.resetInput();
        this.editMode = false;
    }

    public resetInput() {
        this.inputName.reset(this.recordPart());
        this.inputType.reset(this.entry.type);
        this.inputContent.reset(this.entry.content);
        this.inputPriority.reset(this.entry.priority);
        this.inputTtl.reset(this.entry.ttl);
    }

    public async setupFormControls() {
        this.inputName = this.fb.control('');
        this.inputType = this.fb.control('');
        this.inputContent = this.fb.control('');
        this.inputPriority = this.fb.control('', [Validators.required, Validators.pattern(/^[0-9]+$/)]);
        this.inputTtl = this.fb.control('', [Validators.required, Validators.pattern(/^[0-9]+$/)]);
    }

    public async onEditClick() {
        this.editMode = !this.editMode;
        this.resetInput();
        this.entry.new = false;
    }

    public domainPart(): string {
        return '.' + this.domain.name;
    }

    public recordPart(): string {
        const pos = this.entry.name.lastIndexOf(this.domain.name);
        return this.entry.name.substr(0, pos).replace(/\.$/, '');
    }

    public fullName(): string {
        if (this.inputName.value !== '') {
            return this.inputName.value + '.' + this.domain.name;
        } else {
            return this.domain.name;
        }
    }

    public async onSave(ptr) {
        this.entry.new = false;
        const res = await this.records.updateRecord(this.entry.id, ptr, this.fullName(),
            this.inputType.value, this.inputContent.value, this.inputPriority.value, this.inputTtl.value);
        if (!res) return;
        if (res == 201) {
            const updentry = await this.records.getSingle(this.entry.id);
            Object.assign(this.entry, updentry);
            this.entry.new = true;
        }
        this.editMode = false;
        this.recordUpdated.emit();
    }

    public async onDeleteClick(event) {
        try {
            if (!event.shiftKey) {
                await this.modal.showMessage(new ModalOptionsDatatype({
                    heading: 'Confirm deletion',
                    body: 'Are you sure you want to delete the ' + this.inputType.value +
                        ' record ' + this.fullName() + ' with content ' + this.inputContent.value + '?',
                    acceptText: 'Delete',
                    dismisText: 'Cancel',
                    acceptClass: 'danger'
                }));
            }

            await this.records.delete(this.entry.id);

            this.recordDeleted.emit(this.entry.id);
        } catch (e) {
        }
    }

    public async onRemoteClick() {
        this.router.navigate(['./records', this.entry.id.toString(), 'credentials'], { relativeTo: this.route });
    }

    public async onToggleClick() {
        this.entry.new = false;
        if (!await this.records.updateRecord(this.entry.id, false, null, null, null, null, null, !this.entry.disabled)) {
            return;
        }

        this.entry.disabled = !this.entry.disabled;
        this.recordUpdated.emit();
    }
}
