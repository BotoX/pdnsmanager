import { LoggingOperation } from './../../operations/logging.operations';
import { LoggingApitype } from './../../apitypes/Logging.apitype';
import { SortEventDatatype } from './../../datatypes/sort-event.datatype';
import { ModalOptionsDatatype } from './../../datatypes/modal-options.datatype';
import { ModalService } from './../../services/modal.service';
import { StateService } from './../../services/state.service';
import { PagingApitype } from './../../apitypes/Paging.apitype';
import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { FormControl } from '@angular/forms';

import 'rxjs/add/operator/debounceTime';

@Component({
    selector: 'app-logging',
    templateUrl: './logging.component.html',
    styleUrls: ['./logging.component.scss']
})
export class LoggingComponent implements OnInit {

    public pagingInfo = new PagingApitype({});
    public pageRequested = 1;

    public logList: LoggingApitype[] = [];

    public sortField = 'id';
    public sortOrder = 'desc';

    public domainFilter: FormControl;
    public logFilter: FormControl;
    public userFilter: FormControl;

    constructor(private logs: LoggingOperation, public gs: StateService, private modal: ModalService, private router: Router) { }

    public ngOnInit() {
        this.domainFilter = new FormControl('');
        this.domainFilter.valueChanges.debounceTime(500).subscribe(() => this.loadData());

        this.logFilter = new FormControl('');
        this.logFilter.valueChanges.debounceTime(500).subscribe(() => this.loadData());

        this.userFilter = new FormControl('');
        this.userFilter.valueChanges.debounceTime(500).subscribe(() => this.loadData());

        this.loadData();
    }

    public async loadData() {
        const sortStr = this.sortField !== '' ? this.sortField + '-' + this.sortOrder : null;
        const domainFilter = this.domainFilter.value !== '' ? this.domainFilter.value : null;
        const logFilter = this.logFilter.value !== '' ? this.logFilter.value : null;
        const userFilter = this.userFilter.value !== '' ? this.userFilter.value : null;

        const res = await this.logs.getList(this.pageRequested, this.gs.pageSize, domainFilter, logFilter, sortStr, userFilter);

        if (res.paging.total < this.pageRequested && res.paging.total > 0) {
            this.pageRequested = Math.max(1, res.paging.total);
            await this.loadData();
        } else {
            this.pagingInfo = res.paging;
            this.logList = res.results;
        }
    }

    public async onPageChange(newPage: number) {
        this.pageRequested = newPage;
        await this.loadData();
    }

    public async onPagesizeChange(pagesize: number) {
        this.gs.pageSize = pagesize;
        this.pageRequested = 1;
        await this.loadData();
    }

    public async OnLogClick(log: LoggingApitype) {
    }

    public async onSortEvent(sortEvent: SortEventDatatype) {
        if (sortEvent.order === 0) {
            this.sortField = 'id';
            this.sortOrder = 'desc';
        } else {
            this.sortField = sortEvent.field;
            this.sortOrder = sortEvent.order === 1 ? 'asc' : 'desc';
        }

        await this.loadData();
    }
}
