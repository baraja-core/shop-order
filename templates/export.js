Vue.component('cms-order-vat-export', {
	template: `<div>
	<h1>Export DPH</h1>
	<div v-if="this.statuses === null" class="text-center my-5">
		<b-spinner></b-spinner>
	</div>
	<b-card v-else>
		<div class="row mb-3">
			<div class="col-4">
				From:
				<b-form-datepicker v-model="form.dateFrom" class="mb-2"></b-form-datepicker>
			</div>
			<div class="col-4">
				To:
				<b-form-datepicker v-model="form.dateTo" class="mb-2"></b-form-datepicker>
			</div>
			<div class="col-4">
				Filter by:
				<b-form-select v-model="form.filterBy" :options="filterByOptions"></b-form-select>
			</div>
		</div>
		<div class="mb-3">
			Statuses:
			<b-form-select v-model="form.statuses" :options="statuses" :select-size="4" multiple></b-form-select>
			<b-button @click="setVatReportStatuses" size="sm">Set VAT statuses</b-button>
		</div>
		<b-button @click="exportCsv">Export CSV</b-button>
	</b-card>
</div>`,
	data() {
		return {
			statuses: null,
			form: {
				dateFrom: '',
				dateTo: '',
				filterBy: 'insertedDate',
				statuses: []
			},
			filterByOptions: [
				{value: 'insertedDate', text: 'Order inserted date'},
				{value: 'invoiceDate', text: 'Invoice inserted date (DUZP)'}
			]
		}
	},
	created() {
		this.sync();
	},
	methods: {
		sync() {
			axiosApi.get('cms-order-vat/statuses')
				.then(req => {
					this.statuses = req.data.list;
				});
		},
		setVatReportStatuses(){
			this.form.statuses = [
				'company',
				'done',
				'missing-item',
				'prepared',
				'returned',
				'sent',
			];
		},
		exportCsv() {
			window.location.href = baseApiPath + '/cms-order-vat/export?' + httpBuildQuery(this.form);
		}
	}
});
