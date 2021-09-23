Vue.component('cms-order-default', {
	template: `<div class="container-fluid">
	<div class="row mt-2">
		<div class="col">
			<h1>Order manager</h1>
		</div>
		<div class="col-sm-3 text-right">
			<b-button variant="success" v-b-modal.modal-create-order>New order</b-button>
		</div>
	</div>
	<div v-if="items === null" class="text-center py-5">
		<b-spinner></b-spinner>
	</div>
	<template v-else>
		<cms-filter>
			<b-form inline class="w-100">
				<div class="w-100">
					<div class="d-flex flex-column flex-sm-row align-items-sm-center pr-lg-0">
						<div class="row w-100">
							<div class="col">
								<b-form-input size="sm" v-model="filter.query" @input="sync" class="mr-3 w-100" style="max-width:300px" placeholder="Search anywhere..."></b-form-input>
								<b-form-select size="sm" v-model="filter.group" :options="filterGroups" @change="sync" style="width:85px"></b-form-select>
								<b-form-select size="sm" v-model="filter.status" :options="filterStatuses" @change="sync" style="width:164px"></b-form-select>
								<b-form-select size="sm" v-model="filter.delivery" :options="filterDeliveries" @change="sync" style="width:128px"></b-form-select>
								<b-form-select size="sm" v-model="filter.payment" :options="filterPayments" @change="sync" style="width:128px"></b-form-select>
								<b-form-select size="sm" v-model="filter.orderBy" :options="orderByOptions" @change="sync" style="width:128px"></b-form-select>
								<b-form-datepicker size="sm" v-model="filter.dateFrom" @input="sync" style="display:inline-block !important"></b-form-datepicker>
								<b-form-datepicker size="sm" v-model="filter.dateTo" @input="sync" style="display:inline-block !important"></b-form-datepicker>
							</div>
							<div class="col-1 text-right">
								<b-form-select size="sm" v-model="filter.limit" :options="limitOptions" @change="sync"></b-form-select>
							</div>
						</div>
					</div>
				</div>
			</b-form>
		</cms-filter>
		<b-card>
			<div class="row">
				<div class="col">
					Count: <b>{{ paginator.itemCount }}</b>
					| Sum of&nbsp;view: <b>{{ sum }}&nbsp;Kč</b>
				</div>
				<div class="col-sm-2 text-right">
					<b-button @click="sendPackets()" variant="secondary" size="sm" v-b-tooltip.hover title="Clicking the button will establish shipments with the carrier for all orders displayed. This action cannot be reverted.">
						Create packages
					</b-button>
				</div>
				<div class="col-sm-2">
					<b-pagination
						v-model="paginator.page"
						:per-page="paginator.itemsPerPage"
						@change="syncPaginator()"
						:total-rows="paginator.itemCount" align="right" size="sm">
					</b-pagination>
				</div>
			</div>
			<table class="table table-sm cms-table-no-border-top">
				<tr>
					<th>
						<b-button variant="secondary" size="sm" class="px-1 py-0" @click="markAll()">all</b-button>
					</th>
					<th>Number</th>
					<th style="width:150px">Status</th>
					<th>Price</th>
					<th>Customer</th>
					<th>Items</th>
					<th>Delivery</th>
					<th>Payment</th>
					<th>Documents</th>
				</tr>
				<tr v-for="item in items">
					<td>
						<b-form-checkbox v-model="item.checked"></b-form-checkbox>
					</td>
					<td>
						<a :href="link('CmsOrder:detail', {id: item.id})">{{ item.number }}</a><br>
						<span class="badge badge-secondary">{{ item.insertedDate }}</span><br>
						<span class="badge badge-secondary">{{ item.updatedDate }}</span>
					</td>
					<td :class="{ 'table-primary': item.status.code === 'new', 'table-success': item.status.code === 'paid' }">
						<b-form-select v-model="item.status.code" :options="statuses" size="sm" @change="changeStatus(item.id, item.status.code)" style="margin-bottom:5px;height:8px"></b-form-select>
						<span class="badge badge-secondary" :style="'background:' + item.status.color">{{ item.status.label }}</span>
					</td>
					<td class="text-center">
						<template v-if="item.sale > 0">
							<s class="text-danger">{{ item.price }}&nbsp;Kč</s><br>
							<b>{{ item.finalPrice }}&nbsp;Kč</b>
						</template>
						<template v-else>
							{{ item.price }}&nbsp;Kč
						</template>
					</td>
					<td>
						<a :href="link('Customer:detail', {id: item.customer.id})">
							{{ item.customer.firstName }}
							{{ item.customer.lastName }}
						</a>
						<br>
						<span style="font-size:10pt">{{ item.customer.email }}</span>
						<span v-if="item.customer.phone" style="font-size:10pt"><br>{{ item.customer.phone }}</span>
					</td>
					<td class="p-0">
						<table class="w-100" cellspacing="0" cellpadding="0" style="font-size:10pt">
							<tr v-for="orderItem in item.items">
								<td class="text-right" width="32">
									<template v-if="orderItem.count === 1">1</template>
									<span v-else class="badge badge-danger px-1 py-0" style="font-size:11pt">{{ orderItem.count }}</span>
								</td>
								<td style="padding:2px 0">{{ orderItem.name }}</td>
								<td class="text-right">
									<template v-if="orderItem.sale > 0">
										<s class="text-danger">{{ orderItem.price }}&nbsp;Kč</s><br>
										<b>{{ orderItem.finalPrice }}&nbsp;Kč</b>
									</template>
									<template v-else>
										{{ orderItem.price }}&nbsp;Kč
									</template>
								</td>
							</tr>
						</table>
						<div v-if="item.notice" class="card p-1" style="font-size:10pt;background:#eee">
							<span>{{ item.notice }}</span>
						</div>
					</td>
					<td :class="{ 'table-success': item.package }">
						<span class="badge badge-secondary" :style="'background:' + item.delivery.color">{{ item.delivery.name }}</span>
						<br>{{ item.delivery.price }}&nbsp;Kč
						<div v-if="item.package">
							<span class="badge badge-success">PACKAGE READY</span>
						</div>
					</td>
					<td>
						<span class="badge badge-secondary" :style="'background:' + item.payment.color">{{ item.payment.name }}</span>
						<br>{{ item.payment.price }}&nbsp;Kč
					</td>
					<td>
						<div v-for="invoice in item.invoices">
							<a :href="invoice.url" target="_blank">{{ invoice.number }}</a>
						</div>
					</td>
				</tr>
			</table>
			<b-pagination
				v-model="paginator.page"
				:per-page="paginator.itemsPerPage"
				@change="syncPaginator()"
				:total-rows="paginator.itemCount" align="center" size="sm">
			</b-pagination>
		</b-card>
	</template>
	<b-modal id="modal-create-order" title="Create a new order" size="lg" @shown="openCreateOrder" hide-footer>
		<div v-if="customerList === null" class="text-center my-5">
			<b-spinner></b-spinner>
		</div>
		<template v-else>
			<b-form-input v-model="customerListSearch" @input="openCreateOrder" placeholder="Vyhledávejte zákazníky..."></b-form-input>
			<table class="table table-sm my-3">
				<tr>
					<th>ID</th>
					<th>Jméno</th>
					<th></th>
				</tr>
				<tr v-for="customer in customerList">
					<td>{{ customer.id }}</td>
					<td>{{ customer.name }}</td>
					<td class="text-right">
						<b-button size="sm" class="py-0" @click="createOrder(customer.id)">Vytvořit</b-button>
					</td>
				</tr>
			</table>
			<p class="text-secondary">
				Na základě nalezeného zákazníka se vytvoří prázdná objednávka, kterou budete moci editovat.
			</p>
		</template>
	</b-modal>
</div>`,
	data() {
		return {
			items: null,
			customerList: null,
			customerListSearch: '',
			sum: 0,
			paginator: {
				itemsPerPage: 0,
				page: 1,
				itemCount: 0,
			},
			statuses: [],
			filterGroups: [],
			filterStatuses: [],
			filterPayments: [],
			filterDeliveries: [],
			orderByOptions: [
				{value: null, text: 'Latest'},
				{value: 'old', text: 'Oldest'},
				{value: 'number', text: 'Number ASC'},
				{value: 'number-desc', text: 'Number DESC'},
			],
			limitOptions: [
				{value: 32, text: '32'},
				{value: 64, text: '64'},
				{value: 128, text: '128'},
				{value: 256, text: '256'},
				{value: 512, text: '512'},
				{value: 1024, text: '1024'},
				{value: 2048, text: '2048'}
			],
			filter: {
				query: '',
				group: null,
				orderBy: null,
				status: null,
				delivery: null,
				payment: null,
				limit: 128,
				dateFrom: null,
				dateTo: null
			}
		}
	},
	created() {
		this.sync();
		setInterval(this.sync, 10000);
	},
	methods: {
		sync: function () {
			let query = {
				query: this.filter.query ? this.filter.query : null,
				status: this.filter.status ? this.filter.status : null,
				delivery: this.filter.delivery ? this.filter.delivery : null,
				payment: this.filter.payment ? this.filter.payment : null,
				orderBy: this.filter.orderBy ? this.filter.orderBy : null,
				page: this.paginator.page,
				limit: this.filter.limit,
				dateFrom: this.filter.dateFrom,
				dateTo: this.filter.dateTo
			};
			axiosApi.get('cms-order?' + httpBuildQuery(query))
				.then(req => {
					this.items = req.data.items;
					this.sum = req.data.sum;
					this.paginator = req.data.paginator;
					this.statuses = req.data.statuses;
					this.filterGroups = req.data.filterGroups;
					this.filterStatuses = req.data.filterStatuses;
					this.filterPayments = req.data.filterPayments;
					this.filterDeliveries = req.data.filterDeliveries;
					if (this.filter.group === null) {
						this.filter.group = req.data.defaultGroup;
					}
				});
		},
		changeStatus(id, status) {
			axiosApi.post('cms-order/change-status', {
				id: id,
				status: status
			}).then(req => {
				this.sync();
			});
		},
		syncPaginator() {
			setTimeout(this.sync, 50);
		},
		sendPackets() {
			axiosApi.post('cms-order/process-packet-multiple', {
				items: this.items
			}).then(req => {
				this.sync();
			});
		},
		markAll() {
		},
		openCreateOrder() {
			if (this.customerList === null || this.customerListSearch !== '') {
				axiosApi.get('cms-order/customer-list?query=' + this.customerListSearch)
					.then(req => {
						this.customerList = req.data.items;
					});
			}
		},
		createOrder(customerId) {
			axiosApi.post('cms-order/create-empty-order', {
				customerId: customerId
			}).then(req => {
				window.location.href = link('CmsOrder:detail', {id: req.data.id});
			});
		}
	}
});
