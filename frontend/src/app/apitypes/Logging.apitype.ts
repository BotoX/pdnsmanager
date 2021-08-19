export class LoggingApitype {

    public id: number = null;

    public domain: number = null;
    public domain_name: string = null;

    public user: number = null;
    public user_name: string = null;

    public timestamp: Date = null;

    public log: string = null;

    constructor(init: Object) {
        Object.assign(this, init);
    }
}
