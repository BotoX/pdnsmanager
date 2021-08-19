import { LoggingApitype } from './../apitypes/Logging.apitype';
import { ListApitype } from './../apitypes/List.apitype';
import { Injectable } from '@angular/core';
import { HttpService } from '../services/http.service';
import { StateService } from '../services/state.service';

@Injectable()
export class LoggingOperation {

    constructor(private http: HttpService, private gs: StateService) { }

    public async getList(page?: number, pageSize?: number, domain?: string, log?: string,
        sort?: Array<String> | string, user?: string): Promise<ListApitype<LoggingApitype>> {
        try {
            return new ListApitype<LoggingApitype>(await this.http.get('/logging', {
                page: page,
                pagesize: pageSize,
                domain: domain,
                log: log,
                sort: sort,
                user: user
            }));
        } catch (e) {
            console.error(e);
            return new ListApitype<LoggingApitype>({ paging: {}, results: [] });
        }
    }
}
