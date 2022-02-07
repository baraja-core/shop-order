Vue.component('cms-order-overview', {
	props: ['id'],
	template: `<cms-card>
	<div v-if="order === null" class="text-center my-5">
		<b-spinner></b-spinner>
	</div>
	<div v-else class="container-fluid">
		<div class="row">
			<div class="col">
				Order number:<br>
				<div class="card px-3 py-1"><b>{{ order.number }}</b></div>
			</div>
			<div class="col">
				Status:
				<b-form-select v-model="order.status" :options="order.statuses" size="sm" @change="changeStatus"></b-form-select>
			</div>
			<div class="col">
				Price sum:
				<div class="row">
					<div class="col">
						<div :class="{ 'card': true, 'px-3': true, 'py-1': true, 'alert-success': Math.abs(order.price) <= 0.001 }"><b>{{ order.price }}</b></div>
					</div>
					<div class="col-3">
						<b style="font-size:18pt">{{ order.currency }}</b>
					</div>
				</div>
			</div>
			<div class="col-1 text-right">
				<b-button variant="primary" @click="save">Save</b-button>
			</div>
		</div>
		<div class="my-3">
			<div class="row">
				<div class="col">
					<b-card>
						<div class="row">
							<div class="col">
								<h5>Items</h5>
							</div>
							<div class="col-8 text-right">
								<b-button variant="secondary" size="sm" v-b-modal.modal-add-product>Add product</b-button>
								<b-button variant="secondary" size="sm" v-b-modal.modal-add-virtual-item>Add virtual item</b-button>
							</div>
						</div>
						<table class="table table-sm cms-table-no-border-top">
							<tr>
								<th width="20">#</th>
								<th>Label</th>
								<th width="64">VAT</th>
								<th width="64">Count</th>
								<th width="150"><span v-b-tooltip.hover title="Price per one item.">U.&nbsp;price</span></th>
								<th width="90">Price</th>
								<th></th>
							</tr>
							<tr v-for="item in order.items">
								<td width="20" style="padding:0 !important">
									<template v-if="item.id">
										<div style="position:absolute;margin-top:20px;font-size:8pt;-webkit-transform:rotate(-90deg);transform:rotate(-90deg)">{{ item.id }}</div>
									</template>
								</td>
								<td>
									<template v-if="item.type === 'product'">
										<template v-if="item.productId !== null">
											<a :href="link('Product:detail', {id: item.productId})" target="_blank">
												{{ item.name }}
											</a>
											<i v-if="item.variantId === null" class="text-warning">(regular)</i>
										</template>
										<template v-else>
											<strong>{{ item.name }}</strong>
											<i class="text-warning">(virtual)</i>
										</template>
									</template>
									<template v-if="item.type === 'delivery'">
										<b-form-select v-model="order.deliveryId" :options="order.deliveryList" size="sm" @change="changeDeliveryAndPayments"></b-form-select>
									</template>
									<template v-if="item.type === 'payment'">
										<b-form-select v-model="order.paymentId" :options="order.paymentList" size="sm" @change="changeDeliveryAndPayments"></b-form-select>
									</template>
								</td>
								<td>
									<template v-if="item.type === 'product'">
										<b-form-input v-model="item.vat" type="number" min="1" max="100" size="sm" @change="changeItems"></b-form-input>
									</template>
								</td>
								<td>
									<template v-if="item.type === 'product'">
										<b-form-input v-model="item.count" type="number" min="1" max="100" size="sm" @change="changeItems"></b-form-input>
									</template>
									<template v-else>
										{{ item.count }}
									</template>
								</td>
								<td>
									<template v-if="item.type === 'delivery'">
										<b-form-input type="number" v-model="order.deliverPrice" size="sm"></b-form-input>
									</template>
									<template v-else>
										<span v-if="item.price === 0" class="text-success">FREE</span>
										<template v-else>
											<div role="group" class="input-group mb-2 mr-sm-2 mb-sm-0">
												<b-form-input v-model="item.price" @change="changeItems" type="number" size="sm" :class="{ 'alert-danger': item.sale > 0 }"></b-form-input>
												<div class="input-group-append">
													<div class="input-group-text py-0">{{ order.currency }}</div>
												</div>
											</div>
											<template v-if="item.sale > 0">
												<b class="text-danger">NEW:&nbsp;{{ item.finalPrice }}&nbsp;{{ order.currency }}</b>
											</template>
										</template>
									</template>
									<div v-if="item.type === 'product'">
										<b-button class="btn-sm py-0" style="font-size:10pt" @click="setItemSale(item.id)">Set sale</b-button>
									</div>
								</td>
								<td>
									<span v-if="(item.count * item.price) === 0" class="text-success">FREE</span>
									<template v-else>
										<s v-if="item.sale > 0">{{ item.count * item.price }}&nbsp;{{ order.currency }}</s>
										<template v-else>{{ item.count * item.price }}&nbsp;{{ order.currency }}</template>
									</template>
								</td>
								<td class="text-right">
									<template v-if="item.type === 'product'">
										<b-button variant="outline-danger" @click="removeItem(item.id)" size="sm" class="p-0">🗑️</b-button>
									</template>
								</td>
							</tr>
						</table>
						<b-alert v-if="order.sale > 0" :show="true">
							A discount has been set on the entire order: <b>{{ order.sale }}&nbsp;{{ order.currency }}</b>
							<div class="mt-3">
								<b-button size="sm" @click="setOrderSale()">Change the discount amount or cancel</b-button>
							</div>
						</b-alert>
						<div v-else class="text-right">
							<b-button size="sm" @click="setOrderSale()">Set a discount for the entire order</b-button>
						</div>
					</b-card>
					<b-card class="mt-3">
						<h5>Customer</h5>
						<table class="table table-sm">
							<tr>
								<td style="border-top:0"><strong>Name</strong></td>
								<td style="border-top:0">
									<a :href="link('Customer:detail', {id: order.customer.id})" target="_blank">
										{{ order.customer.name }} ({{ order.customer.id }})
									</a>
								</td>
							</tr>
							<tr>
								<td><strong>E-mail</strong></td>
								<td>{{ order.customer.email }}</td>
							</tr>
							<tr>
								<td><strong>Phone</strong></td>
								<td>{{ order.customer.phone }}</td>
							</tr>
						</table>
						<div class="row">
							<div class="col">
								<div class="alert alert-secondary px-2 my-0">
									<h5>Delivery address</h5>
									<cms-order-overview-address :address="order.deliveryAddress" :country-list="countryList"></cms-order-overview-address>
								</div>
							</div>
							<div class="col">
								<div class="alert alert-warning px-2 my-0">
									<h5>Invoice address</h5>
									<cms-order-overview-address :address="order.invoiceAddress" :country-list="countryList"></cms-order-overview-address>
								</div>
							</div>
						</div>
						<b-button variant="secondary" size="sm" @click="saveAddress()" class="mt-2">
							Save addresses and re-generate invoice
						</b-button>
					</b-card>
					<b-card class="mt-3">
						<h5>Payment</h5>
						<b>Bank transfers:</b>
						<p v-if="order.transactions.length === 0" class="text-secondary">
							No records.
						</p>
						<table v-else class="table table-sm cms-table-no-border-top">
							<tr>
								<th>#</th>
								<th>Price</th>
								<th>Date</th>
							</tr>
							<tr v-for="transaction in order.transactions">
								<td>{{ transaction.id }}</td>
								<td>{{ transaction.price }}</td>
								<td>{{ transaction.date }}</td>
							</tr>
						</table>
						<b>Online payments:</b>
						<p v-if="order.payments.length === 0" class="text-secondary">
							No records.
						</p>
						<template v-else>
							<table class="table table-sm cms-table-no-border-top">
								<tr>
									<th><span v-b-tooltip.hover title="Internal gateway identifier for this payment">Gateway ID</span></th>
									<th>Price</th>
									<th><span v-b-tooltip.hover title="The actual payment status claimed by the payment gateway.">Status</span></th>
									<th><span v-b-tooltip.hover title="The date and time this payment was entered into the system.">Date</span></th>
									<th><span v-b-tooltip.hover title="Date and time of the last check of this payment.">Last check</span></th>
								</tr>
								<tr v-for="payment in order.payments">
									<td>{{ payment.gopayId }}</td>
									<td v-html="payment.price"></td>
									<td :class="{ 'table-danger': !payment.status, 'text-danger': !payment.status }">
										{{ payment.status ? payment.status : 'WAITING' }}
									</td>
									<td>{{ payment.insertedDate }}</td>
									<td>{{ payment.lastCheckedDate }}</td>
								</tr>
							</table>
							<i>Payment status may change over time depending on system or user activity.</i>
						</template>
					</b-card>
					<div class="mt-3">
						<b-button variant="primary" @click="save">Save</b-button>
					</div>
				</div>
				<div class="col-sm-3">
					<b-card :class="{ 'bg-warning': order.notice }">
						<h5>Customer notice</h5>
						<b-form-textarea v-model="order.notice"></b-form-textarea>
					</b-card>
					<b-card class="mt-3">
						<div class="row">
							<div class="col">
								<h5>Invoice</h5>
							</div>
							<div class="col-4 text-right">
								<b-button variant="secondary" size="sm" @click="createInvoice">Create</b-button>
							</div>
						</div>
						<p v-if="order.invoiceNumber === null" class="text-secondary">No&nbsp;invoice.</p>
						<p v-else>{{ order.invoiceNumber }}</p>
					</b-card>
					<b-card class="mt-3">
						<div class="row">
							<div class="col">
								<h5>
									Branch
									<template v-if="order.deliveryBranch">({{ order.deliveryBranch }})</template>
								</h5>
							</div>
							<div class="col-sm-4 text-right">
								<b-button variant="secondary" size="sm" @click="document.getElementById('zasilkovna-open-button').click()">Change</b-button>
							</div>
						</div>
						<div v-if="loading.deliveryBranch && order.deliveryBranch !== null" class="text-center my-5">
							<b-spinner></b-spinner>
						</div>
						<template v-else>
							<template v-if="order.deliveryBranch !== null">
								<div class="alert alert-warning" v-if="deliveryBranch.branch === null">
									The branch has not been selected.
								</div>
								<template v-else>
									<template v-if="deliveryBranch.error === true">
										<div class="alert alert-danger">
											Attention: Customer has chosen a branch that is not available.<br><br>
											Branch ID: <strong>{{ deliveryBranch.branch.id }}</strong>
										</div>
									</template>
									<template v-else>
										<p><i>{{ deliveryBranch.branch.name }}</i></p>
										<div class="text-center">
											<a :href="deliveryBranch.branch.mapsUrl" target="_blank">
												<img :src="deliveryBranch.branch.mapsStaticUrl" referrerpolicy="no-referrer">
											</a><br>
											<i>({{ deliveryBranch.branch.latitude }}, {{ deliveryBranch.branch.longitude }})</i>
										</div>
										<!-- <a :href="deliveryBranch.branch.url" target="_blank">More info</a> -->
									</template>
								</template>
							</template>
							<p v-else class="text-secondary mb-0">Not used.</p>
							<b-card v-if="zasilkovna.id !== ''" class="mt-3">
								Selected branch <strong>{{ zasilkovna.id }}</strong>:<br>
								{{ zasilkovna.name }}<br>
								<b-button variant="success" class="mt-3" @click="savePacketa()">Save branch</b-button>
							</b-card>
						</template>
					</b-card>
					<b-card class="mt-3">
						<h5>Workflow notification</h5>
						<template v-for="notificationItem in order.notifications">
							<b-button variant="secondary" size="sm" class="mr-1 mb-1" @click="sendEmail(notificationItem.id)">
								{{ notificationItem.label }}
								<template v-if="notificationItem.type === 'sms'">📱</template>
								<template v-else-if="notificationItem.type === 'email'">📧</template>
								<template v-else>({{ notificationItem.type }})</template>
							</b-button>
						</template>
					</b-card>
				</div>
			</div>
		</div>
		<b-card>
			<div class="row">
				<div class="col">
					<h5>Package delivery</h5>
				</div>
				<div v-if="order.packageHandoverUrl" class="col text-right">
					<a :href="order.packageHandoverUrl" target="_blank">
						<b-button variant="secondary" size="sm" class="py-0">List of cods</b-button>
					</a>
				</div>
			</div>
			<template v-if="order.package === null">
				<p>
					There is no package with the carrier for this order yet.
					Clicking on the button will create a binding shipment order with the selected carrier
					according to the shipping method.
					The package can no longer be edited.
					If you change the contents of the order, this change will no longer be propagated
					to the carrier and a new shipment will need to be created.
					This action is non-reversible.
				</p>
				<template v-if="loading.createPackage === true">
					<b-spinner class="my-3"></b-spinner>
				</template>
				<template v-else>
					<b-button variant="warning" @click="createPackage()">Create a package at the carrier</b-button>
					<p><b>Warning:</b> The action cannot be revoked.</p>
				</template>
			</template>
			<template v-else>
				<table class="table table-sm table-hover cms-table-no-border-top">
					<tr>
						<th>Order ID</th>
						<th>Package ID</th>
						<th>Shipper</th>
						<th>Carrier ID</th>
						<th>Track</th>
						<th>LAbel</th>
						<th>Carrier swap</th>
					</tr>
					<tr v-for="package in order.package">
						<td>{{ package.orderId }}</td>
						<td>{{ package.packageId }}</td>
						<td>{{ package.shipper }}</td>
						<td>{{ package.carrierId }}</td>
						<td>
							<template v-if="package.trackUrl">
								<a :href="package.trackUrl" target="_blank">
									<b-button variant="secondary" size="sm" class="py-0">Track</b-button>
								</a>
							</template>
							<i v-else>-</i>
						</td>
						<td>
							<template v-if="package.labelUrl">
								<a :href="package.labelUrl" target="_blank">
									<b-button variant="secondary" size="sm" class="py-0">Print</b-button>
								</a>
							</template>
							<i v-else>-</i>
						</td>
						<td>{{ package.carrierIdSwap }}</td>
					</tr>
				</table>
			</template>
		</b-card>
	</div>
	<b-modal id="modal-add-product" title="Add new product" size="lg" @shown="openAddItemModal" hide-footer>
		<div v-if="addItemList === null" class="text-center my-5">
			<b-spinner></b-spinner>
		</div>
		<template v-else>
			<p>Select an item to add to your order. Adding an item will cause the price of the entire order to be recalculated.</p>
			<table class="table table-sm cms-table-no-border-top">
				<tr v-for="item in addItemList">
					<td>
						<a :href="basePath + '/' + item.slug + '.html'" target="_blank">{{ item.name }}</a>
						<div v-if="item.variants.length > 0" class="ml-4">
							<table class="w-100">
								<tr v-for="variantItem in item.variants">
									<td>{{ variantItem.relationHash }}</td>
									<td>{{ variantItem.price ? variantItem.price + ' ' + order.currency : '---' }}</td>
									<td class="text-right">
										<b-button variant="secondary" size="sm" class="btn-sm py-0" @click="addItem(item.id, variantItem.id)">+</b-button>
									</td>
								</tr>
							</table>
						</div>
					</td>
					<td>{{ item.price }}&nbsp;{{ order.currency }}</td>
					<td class="text-right">
						<template v-if="item.variants.length === 0">
							<b-button variant="secondary" size="sm" @click="addItem(item.id)">+</b-button>
						</template>
					</td>
				</tr>
			</table>
		</template>
	</b-modal>
	<b-modal id="modal-add-virtual-item" title="Add new virtual item" hide-footer>
		<b-form @submit="addVirtualItem">
			<div class="mb-3">
				Name:
				<b-form-input v-model="virtualItemForm.name"></b-form-input>
			</div>
			<div class="mb-3">
				Price:
				<b-input-group :append="order.currency" class="mb-2 mr-sm-2 mb-sm-0">
					<b-form-input type="number" v-model="virtualItemForm.price"></b-form-input>
				</b-input-group>
			</div>
			<b-button type="submit" variant="primary" class="mt-3">Add product</b-button>
		</b-form>
	</b-modal>
	<div id="zasilkovna-open-button" class="packeta-selector-open" style="display:none"></div>
	<div id="packeta-selector-branch-id" class="packeta-selector-branch-id" style="display:none"></div>
	<div id="packeta-selector-branch-name" class="packeta-selector-branch-name" style="display:none"></div>
</cms-card>`,
	data() {
		return {
			order: null,
			countryList: [],
			addItemList: null,
			deliveryBranch: {
				branch: null,
				error: null
			},
			loading: {
				createPackage: false,
				deliveryBranch: true
			},
			zasilkovna: {
				name: '',
				id: ''
			},
			virtualItemForm: {
				name: '',
				price: 0
			}
		}
	},
	created() {
		this.sync();
		setInterval(this.syncZasilkovna, 1000);
		this.$nextTick(() => {
			let packetaWidgetScript = document.createElement('script');
			packetaWidgetScript.setAttribute('src', 'https://widget.packeta.com/www/js/packetaWidget.js');
			packetaWidgetScript.setAttribute('data-api-key', 'client API key');
			document.head.appendChild(packetaWidgetScript);
		});
	},
	methods: {
		sync() {
			axiosApi.get(`cms-order/overview?id=${this.id}`)
				.then(req => {
					this.order = req.data;
					this.countryList = req.data.countryList;
					if (this.order.deliveryBranch !== null) {
						axiosApi.get(`cms-order/delivery-branch?id=${this.id}`)
							.then(req => {
								this.deliveryBranch = req.data;
								this.loading.deliveryBranch = false;
							});
					}
				});
		},
		syncZasilkovna() {
			if (document.getElementById('packeta-selector-branch-name').innerHTML) {
				this.zasilkovna.name = document.getElementById('packeta-selector-branch-name').innerHTML + '';
				this.zasilkovna.id = document.getElementById('packeta-selector-branch-id').innerHTML + '';
				this.selectedDeliveryBranch = this.zasilkovna.id;
			}
		},
		changeStatus() {
			axiosApi.post('cms-order/change-status', {
				id: this.id,
				status: this.order.status
			}).then(() => {
				this.sync();
			});
		},
		changeDeliveryAndPayments() {
			axiosApi.post('cms-order/change-delivery-and-payment', {
				id: this.id,
				deliveryId: this.order.deliveryId,
				paymentId: this.order.paymentId
			}).then(() => {
				this.sync();
			});
		},
		changeItems() {
			axiosApi.post('cms-order/change-items', {
				id: this.id,
				items: this.order.items
			}).then(() => {
				this.sync();
			});
		},
		removeItem(id) {
			if (confirm('Do you really want to delete this item?')) {
				axiosApi.post('cms-order/remove-item', {
					orderId: this.id,
					itemId: id
				}).then(() => {
					this.sync();
				});
			}
		},
		openAddItemModal() {
			this.addItemList = null;
			axiosApi.get(`cms-order/items?id=${this.id}`)
				.then(req => {
					this.addItemList = req.data;
				});
		},
		addItem(id, variantId = null) {
			axiosApi.post('cms-order/add-item', {
				orderId: this.id,
				itemId: id,
				variantId: variantId
			}).then(() => {
				this.openAddItemModal();
				this.sync();
			});
		},
		addVirtualItem(evt) {
			evt.preventDefault();
			if (!this.virtualItemForm.name) {
				alert('Product name is mandatory.');
				return;
			}
			axiosApi.post('cms-order/add-virtual-item', {
				orderId: this.id,
				name: this.virtualItemForm.name,
				price: this.virtualItemForm.price
			}).then(() => {
				this.virtualItemForm = {
					name: '',
					price: 0
				};
				this.$bvModal.hide('modal-add-virtual-item');
				this.sync();
			});
		},
		createInvoice() {
			axiosApi.post('cms-order/create-invoice', {
				id: this.id
			}).then(() => {
				this.sync();
			});
		},
		save() {
			axiosApi.post('cms-order/save', {
				id: this.id,
				notice: this.order.notice,
				price: this.order.price,
				deliverPrice: this.order.deliverPrice
			}).then(() => {
				this.sync();
			});
		},
		sendEmail(notificationId) {
			axiosApi.post('cms-order/send-email', {
				id: this.id,
				notificationId: notificationId
			}).then(() => {
			});
		},
		createPackage() {
			this.loading.createPackage = true;
			axiosApi.post('cms-order/create-package', {
				id: this.id
			}).then(() => {
				this.loading.createPackage = false;
				this.sync();
			});
		},
		setOrderSale() {
			let sale = prompt('Enter the desired discount for the entire order in the currency used (if you do not want to set a discount, type "x"):');
			if (sale === 'x') {
				alert('No discount will be set.');
				return;
			}
			axiosApi.post('cms-order/set-order-sale', {
				id: this.id,
				sale: sale
			}).then(() => {
				this.sync();
			});
		},
		setItemSale(id) {
			let sale = prompt('Enter the desired discount for the selected item in the currency used (if you do not want to set a discount, type "x"):');
			if (sale === 'x') {
				alert('No discount will be set.');
				return;
			}
			axiosApi.post('cms-order/set-item-sale', {
				id: this.id,
				itemId: id,
				sale: sale
			}).then(() => {
				this.sync();
			});
		},
		saveAddress() {
			axiosApi.post('cms-order/save-address', {
				id: this.id,
				deliveryAddress: this.order.deliveryAddress,
				invoiceAddress: this.order.invoiceAddress
			}).then(() => {
				this.sync();
			});
		},
		savePacketa() {
			axiosApi.post('cms-order/set-branch-id', {
				orderId: this.id,
				branchId: this.zasilkovna.id
			}).then(() => {
				document.getElementById('packeta-selector-branch-name').innerHTML = '';
				document.getElementById('packeta-selector-branch-id').innerHTML = '';
				this.sync();
			});
		}
	}
});

Vue.component('cms-order-overview-address', {
	props: ['address', 'countryList'],
	template: `<div>
	<div class="row">
		<div class="col pr-0">
			<small>Firstname:</small>
			<b-form-input v-model="address.firstName" size="sm"></b-form-input>
		</div>
		<div class="col pl-0">
			<small>Lastname:</small>
			<b-form-input v-model="address.lastName" size="sm"></b-form-input>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<small>Street:</small>
			<b-form-input v-model="address.street" size="sm"></b-form-input>
		</div>
	</div>
	<div class="row">
		<div class="col pr-0">
			<small>City:</small>
			<b-form-input v-model="address.city" size="sm"></b-form-input>
		</div>
		<div class="col-4 pl-0">
			<small>ZIP:</small>
			<b-form-input v-model="address.zip" size="sm"></b-form-input>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<small>Country:</small>
			<b-form-select v-model="address.country" :options="countryList" size="sm"></b-form-select>
		</div>
	</div>
	<div class="row">
		<div class="col">
			<small>Company:</small>
			<b-form-input v-model="address.companyName" size="sm"></b-form-input>
		</div>
	</div>
	<div class="row">
		<div class="col pr-0">
			<small>VAT number:</small>
			<b-form-input v-model="address.ic" size="sm"></b-form-input>
		</div>
		<div class="col pl-0">
			<small>TIN:</small>
			<b-form-input v-model="address.dic" size="sm"></b-form-input>
		</div>
	</div>
</div>`
});
