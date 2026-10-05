export function receiptMoney(r,key='total_minor') {
 const digits=String(r[key]??'0').padStart(r.minor_unit+1,'0'),unit=r.minor_unit||0
 const whole=unit?digits.slice(0,-unit):digits
 return whole.replace(/\B(?=(\d{3})+(?!\d))/g,' ')+(unit?','+digits.slice(-unit):'')+' '+({XOF:'FCFA',EUR:'€',USD:'$',CAD:'CA$'}[r.currency]||r.currency)
}
export function receiptDate(value,timezone='UTC') { return new Intl.DateTimeFormat('fr-FR',{dateStyle:'medium',timeStyle:'short',timeZone:timezone}).format(new Date(value)) }
