Vue.component('cms-order-overview', {
	props: ['id'],
	template: `<b-card>
	<div v-if="order === null" class="text-center my-5">
		<b-spinner></b-spinner>
	</div>
	<div v-else class="container-fluid">
		<div class="row">
			<div class="col">
				Číslo objednávky:<br>
				<div class="card px-3 py-1"><b>{{ order.number }}</b></div>
			</div>
			<div class="col">
				Stav:
				<b-form-select v-model="order.status" :options="order.statuses" size="sm" @change="changeStatus"></b-form-select>
			</div>
			<div class="col">
				Cena celkem:
				<div class="row">
					<div class="col">
						<b-form-input v-model="order.price"></b-form-input>
					</div>
					<div class="col-3">
						<b style="font-size:18pt">Kč</b>
					</div>
				</div>
			</div>
			<div class="col-1 text-right">
				<b-button variant="primary" @click="save">Uložit</b-button>
			</div>
		</div>
		<div class="my-3">
			<div class="row">
				<div class="col">
					<b-card>
						<div class="row">
							<div class="col">
								<h5>Položky</h5>
							</div>
							<div class="col-3 text-right">
								<b-button variant="secondary" size="sm" v-b-modal.modal-add-item>Nová položka</b-button>
							</div>
						</div>
						<table class="table table-sm">
							<tr>
								<th width="20">ID</th>
								<th>Název</th>
								<th width="64">Mn.</th>
								<th width="90">J.&nbsp;cena</th>
								<th width="90">Cena</th>
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
										<a :href="link('Product:detail', {id: item.productId})" target="_blank">
											{{ item.name }}
										</a>
										<i v-if="item.variantId === null" class="text-warning">(není variantní)</i>
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
										<b-form-input v-model="item.count" type="number" min="1" max="100" size="sm" @change="changeCount"></b-form-input>
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
										<span v-if="item.price === 0" class="text-success">ZDARMA</span>
										<template v-else>
											<template v-if="item.sale > 0">
												<s class="text-danger">{{ item.price }}&nbsp;Kč</s><br>
												<b>{{ item.finalPrice }}&nbsp;Kč</b>
											</template>
											<template v-else>
												{{ item.price }}&nbsp;Kč
											</template>
										</template>
									</template>
									<template v-if="item.type === 'product'">
										<b-button class="btn-sm py-0" style="font-size:10pt" @click="setItemSale(item.id)">Dát slevu</b-button>
									</template>
								</td>
								<td>
									<span v-if="(item.count * item.price) === 0" class="text-success">ZDARMA</span>
									<template v-else>{{ item.count * item.price }}&nbsp;Kč</template>
								</td>
								<td class="text-right">
									<template v-if="item.type === 'product'">
										<b-button variant="danger" size="sm" class="px-1 py-0" @click="removeItem(item.id)">x</b-button>
									</template>
								</td>
							</tr>
						</table>
						<b-alert v-if="order.sale > 0" :show="true">
							Na celou objednávku byla nastavena sleva: <b>{{ order.sale }}&nbsp;Kč</b>
							<div class="mt-3">
								<b-button size="sm" @click="setOrderSale()">Změnit výši slevy nebo zrušit</b-button>
							</div>
						</b-alert>
						<div v-else class="text-right">
							<b-button size="sm" @click="setOrderSale()">Nastavit slevu na celou objednávku</b-button>
						</div>
					</b-card>
					<div class="row">
						<div class="col">
							<b-card :class="{ 'mt-3': true, 'bg-warning': order.notice }">
								<h5>Poznámka</h5>
								<b-form-textarea v-model="order.notice"></b-form-textarea>
							</b-card>
						</div>
						<div class="col">
							<b-card class="mt-3">
								<h5>E-maily</h5>
								<b-button variant="secondary" size="sm" @click="sendEmail('new-order')">Přijetí objednávky</b-button>
								<b-button variant="secondary" size="sm" @click="sendEmail('paid')">Zaplaceno</b-button>
								<b-button variant="secondary" size="sm" @click="sendEmail('invoice')">Faktura</b-button>
							</b-card>
						</div>
					</div>
					<div class="mt-3">
						<b-button variant="primary" @click="save">Uložit</b-button>
					</div>
				</div>
				<div class="col">
					<div class="row">
						<div class="col">
							<b-card>
								<h5>Zákazník</h5>
								<table class="table table-sm">
									<tr>
										<th>Jméno</th>
										<td>
											<a :href="link('Customer:detail', {id: order.customer.id})">
												{{ order.customer.name }} ({{ order.customer.id }})
											</a>
										</td>
									</tr>
									<tr>
										<th>E-mail</th>
										<td>{{ order.customer.email }}</td>
									</tr>
									<tr>
										<th>Telefon</th>
										<td>{{ order.customer.phone }}</td>
									</tr>
								</table>
								<div :class="['alert', showDelivery ? 'alert-secondary' : 'alert-warning', 'px-2', 'my-0']">
									<div class="row">
										<div class="col">
											<h5>{{ showDelivery ? 'Doručovací adresa' : 'Fakturační adresa' }}</h5>
										</div>
										<div class="col-4 text-right">
											<b-btn size="sm" class="btn btn-sm py-0" @click="showDelivery=!showDelivery">Přepnout</b-btn>
										</div>
									</div>
									<div>
										<div class="row">
											<div class="col pr-0">
												<small>Jméno:</small>
												<b-form-input v-model="address.firstName" size="sm"></b-form-input>
											</div>
											<div class="col pl-0">
												<small>Příjmení:</small>
												<b-form-input v-model="address.lastName" size="sm"></b-form-input>
											</div>
										</div>
										<div class="row">
											<div class="col">
												<small>Ulice:</small>
												<b-form-input v-model="address.street" size="sm"></b-form-input>
											</div>
										</div>
										<div class="row">
											<div class="col pr-0">
												<small>Město:</small>
												<b-form-input v-model="address.city" size="sm"></b-form-input>
											</div>
											<div class="col-3 p-0">
												<small>PSČ:</small>
												<b-form-input v-model="address.zip" size="sm"></b-form-input>
											</div>
											<div class="col-3 pl-0">
												<small>Země:</small>
												<b-form-input v-model="address.country" size="sm"></b-form-input>
											</div>
										</div>
										<div class="row">
											<div class="col">
												<small>Název firmy:</small>
												<b-form-input v-model="address.companyName" size="sm"></b-form-input>
											</div>
										</div>
										<div class="row">
											<div class="col pr-0">
												<small>IČ:</small>
												<b-form-input v-model="address.ic" size="sm"></b-form-input>
											</div>
											<div class="col pl-0">
												<small>DIČ:</small>
												<b-form-input v-model="address.dic" size="sm"></b-form-input>
											</div>
										</div>
									</div>
								</div>
								<b-button variant="secondary" size="sm" @click="saveAddress()" class="mt-2">Uložit adresy a přegenerovat fakturu</b-button>
							</b-card>
						</div>
						<div class="col">
							<b-card>
								<div class="row">
									<div class="col">
										<h5>Faktura</h5>
									</div>
									<div class="col-4 text-right">
										<b-button variant="secondary" size="sm" @click="createInvoice">Vystavit</b-button>
									</div>
								</div>
								<div v-if="order.invoices.length === 0" class="alert alert-info">
									Nemá žádnou fakturu.
								</div>
								<table v-else class="table table-sm">
									<tr>
										<th>Číslo</th>
										<th>Cena</th>
										<th>Zapl.</th>
										<th>Vystaveno</th>
									</tr>
									<tr v-for="invoice in order.invoices">
										<td>
											<a :href="invoice.url" target="_blank">{{ invoice.number }}</a>
										</td>
										<td>{{ invoice.price }}</td>
										<td>{{ invoice.paid ? 'ano' : 'ne' }}</td>
										<td>{{ invoice.date }}</td>
									</tr>
								</table>
							</b-card>
							<b-card class="mt-3">
								<div class="row">
									<div class="col">
										<h5>Zásilkovna</h5>
									</div>
									<div class="col-4 text-right">
										<b-button variant="secondary" size="sm" @click="document.getElementById('zasilkovna-open-button').click()">Změnit</b-button>
									</div>
								</div>
								<div class="alert alert-warning" v-if="order.deliveryBranch === null">
									Pobočka nebyla vybrána.
								</div>
								<template v-else>
									<template v-if="order.deliveryBranchError === true">
										<div class="alert alert-danger">
											Pozor, zákazník si vybral pobočku, která není dostupná.<br><br>
											ID pobočky: <b>{{ order.deliveryBranch.id }}</b>
										</div>
									</template>
									<template v-else>
										<div>
											Pobočka {{ order.deliveryBranch.id }}<br>
											{{ order.deliveryBranch.nameStreet }}<br>
											<a :href="order.deliveryBranch.url" target="_blank">Více info</a>
										</div>
										<div v-if="order.deliveryBranch.photos.length > 0" class="mt-3">
											<template v-for="branchImage in order.deliveryBranch.photos">
												<a :href="branchImage.normal" target="_blank">
													<img :src="branchImage.thumbnail" class="m-1" style="height:64px">
												</a>
											</template>
										</div>
									</template>
								</template>
								<b-card v-if="zasilkovna.id !== ''" class="mt-3">
									Byla vybrána pobočka <b>{{ zasilkovna.id }}</b>:<br>
									{{ zasilkovna.name }}<br>
									<b-button variant="success" class="mt-3" @click="savePacketa()">Uložit pobočku</b-button>
								</b-card>
							</b-card>
						</div>
					</div>
					<b-card class="mt-3">
						<h5>Platby</h5>
						Bankovní převody:
						<div v-if="order.transactions.length === 0" class="alert alert-info">
							Žádné nejsou.
						</div>
						<table v-else class="table table-sm">
							<tr>
								<th>ID</th>
								<th>Částka</th>
								<th>Datum</th>
							</tr>
							<tr v-for="transaction in order.transactions">
								<td>{{ transaction.id }}</td>
								<td>{{ transaction.price }}</td>
								<td>{{ transaction.date }}</td>
							</tr>
						</table>
						Platby kartou:
						<div v-if="order.payments.length === 0" class="alert alert-info">
							Žádné nejsou.
						</div>
						<table v-else class="table table-sm">
							<tr>
								<th>GoPay ID</th>
								<th>Částka</th>
								<th>Stav</th>
								<th>Datum</th>
							</tr>
							<tr v-for="payment in order.payments">
								<td>{{ payment.gopayId }}</td>
								<td>{{ payment.price }}</td>
								<td>{{ payment.status }}</td>
								<td>{{ payment.insertedDate }}</td>
							</tr>
						</table>
					</b-card>
				</div>
			</div>
		</div>
		<b-card>
			<div class="row">
				<div class="col">
					<h5>Přeprava balíku</h5>
				</div>
				<div v-if="order.packageHandoverUrl" class="col text-right">
					<a :href="order.packageHandoverUrl" target="_blank">
						<b-button variant="secondary" size="sm" class="py-0">Seznam dobírek</b-button>
					</a>
				</div>
			</div>
			<template v-if="order.package === null">
				<p>
					Pro tuto objednávku ještě neexistuje balík u přepravce.
					Kliknutím na tlačítko se závazně vytvoří předpis zásilky u zvoleného přepravce podle způsobu dopravy.
					Balík již nelze editovat.
					Pokud změníte obsah objednávky,
					nebude se tato změna již propagovat k přepravci a bude potřeba vytvořit novou zásilku.
					Tato akce je nevratná.
				</p>
				<template v-if="loading.createPackage === true">
					<b-spinner class="my-3"></b-spinner>
				</template>
				<template v-else>
					<b-button variant="warning" @click="createPackage()">Vytvořit balík u přepravce</b-button>
					<p><b>Pozor:</b> Akci nelze odvolat zpět.</p>
				</template>
			</template>
			<template v-else>
				<table class="table table-sm table-hover">
					<tr>
						<th>Č.&nbsp;objednávky</th>
						<th>Č.&nbsp;balíku</th>
						<th>Dopravce</th>
						<th>Pobočka</th>
						<th>Sledování</th>
						<th>Štítek</th>
						<th>Swap pobočky</th>
					</tr>
					<tr v-for="package in order.package">
						<td>{{ package.orderId }}</td>
						<td>{{ package.packageId }}</td>
						<td>{{ package.shipper }}</td>
						<td>{{ package.carrierId }}</td>
						<td>
							<template v-if="package.trackUrl">
								<a :href="package.trackUrl" target="_blank">
									<b-button variant="secondary" size="sm" class="py-0">Sledovat</b-button>
								</a>
							</template>
							<i v-else>není</i>
						</td>
						<td>
							<template v-if="package.labelUrl">
								<a :href="package.labelUrl" target="_blank">
									<b-button variant="secondary" size="sm" class="py-0">Vytisknout</b-button>
								</a>
							</template>
							<i v-else>není</i>
						</td>
						<td>{{ package.carrierIdSwap }}</td>
					</tr>
				</table>
			</template>
		</b-card>
	</div>
	<b-modal id="modal-add-item" title="Nová položka" size="lg" @shown="openAddItemModal" hide-footer>
		<div v-if="addItemList === null" class="text-center my-5">
			<b-spinner></b-spinner>
		</div>
		<template v-else>
			<p>Vyberte položku k přidání do objednávky. Přidání položky způsobí přepočítání ceny celé objednávky.</p>
			<table class="table table-sm">
				<tr v-for="item in addItemList">
					<td>
						<a :href="basePath + '/' + item.slug + '.html'" target="_blank">{{ item.name }}</a>
						<div v-if="item.variants.length > 0" class="ml-4">
							<table class="w-100">
								<tr v-for="variantItem in item.variants">
									<td>{{ variantItem.relationHash }}</td>
									<td>{{ variantItem.price ? variantItem.price + ' Kč' : '---' }}</td>
									<td class="text-right">
										<b-button variant="secondary" size="sm" class="btn-sm py-0" @click="addItem(item.id, variantItem.id)">+</b-button>
									</td>
								</tr>
							</table>
						</div>
					</td>
					<td>{{ item.price }}&nbsp;Kč</td>
					<td class="text-right">
						<template v-if="item.variants.length === 0">
							<b-button variant="secondary" size="sm" @click="addItem(item.id)">+</b-button>
						</template>
					</td>
				</tr>
			</table>
		</template>
	</b-modal>
	<div id="zasilkovna-open-button" class="packeta-selector-open" style="display:none"></div>
	<div id="packeta-selector-branch-id" class="packeta-selector-branch-id" style="display:none"></div>
	<div id="packeta-selector-branch-name" class="packeta-selector-branch-name" style="display:none"></div>
</b-card>`,
	data() {
		return {
			order: null,
			addItemList: null,
			showDelivery: true,
			loading: {
				createPackage: false
			},
			zasilkovna: {
				name: '',
				id: ''
			},
		}
	},
	created() {
		this.sync();
		setInterval(this.syncZasilkovna, 1000);
		this.$nextTick(() => {
			let packetaWidgetScript = document.createElement('script');
			packetaWidgetScript.setAttribute('src', 'https://widget.packeta.com/www/js/packetaWidget.js');
			packetaWidgetScript.setAttribute('data-api-key', 'klíč API klienta');
			document.head.appendChild(packetaWidgetScript);
		});
	},
	computed: {
		address: function () {
			return this.order[this.showDelivery ? 'deliveryAddress' : 'invoiceAddress'];
		}
	},
	methods: {
		sync() {
			axiosApi.get(`cms-order/overview?id=${this.id}`)
				.then(req => {
					this.order = req.data;
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
			}).then(req => {
				this.sync();
			});
		},
		changeDeliveryAndPayments() {
			axiosApi.post('cms-order/change-delivery-and-payment', {
				id: this.id,
				deliveryId: this.order.deliveryId,
				paymentId: this.order.paymentId
			}).then(req => {
				this.sync();
			});
		},
		changeCount() {
			axiosApi.post('cms-order/change-quantity', {
				id: this.id,
				items: this.order.items
			}).then(req => {
				this.sync();
			});
		},
		removeItem(id) {
			if (confirm('Opravdu chcete smazat tuto položku?')) {
				axiosApi.post('cms-order/remove-item', {
					orderId: this.id,
					itemId: id
				}).then(req => {
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
			}).then(req => {
				this.openAddItemModal();
				this.sync();
			});
		},
		createInvoice() {
			axiosApi.post('cms-order/create-invoice', {
				id: this.id
			}).then(req => {
				this.sync();
			});
		},
		save() {
			axiosApi.post('cms-order/save', {
				id: this.id,
				notice: this.order.notice,
				price: this.order.price,
				deliverPrice: this.order.deliverPrice
			}).then(req => {
				this.sync();
			});
		},
		sendEmail(mail) {
			axiosApi.post('cms-order/send-email', {
				id: this.id,
				mail: mail
			}).then(req => {
			});
		},
		createPackage() {
			this.loading.createPackage = true;
			axiosApi.post('cms-order/create-package', {
				id: this.id
			}).then(req => {
				this.loading.createPackage = false;
				this.sync();
			});
		},
		setOrderSale() {
			let sale = prompt('Zadejte požadovanou slevu v korunách na celou objednávku (pokud slevu nechcete nastavit, napište "x"):');
			if (sale === 'x') {
				alert('Žádná sleva nastavena nebude.');
				return;
			}
			axiosApi.post('cms-order/set-order-sale', {
				id: this.id,
				sale: sale
			}).then(req => {
				this.sync();
			});
		},
		setItemSale(id) {
			let sale = prompt('Zadejte požadovanou slevu v korunách na zvolenou položku (pokud slevu nechcete nastavit, napište "x"):');
			if (sale === 'x') {
				alert('Žádná sleva nastavena nebude.');
				return;
			}
			axiosApi.post('cms-order/set-item-sale', {
				id: this.id,
				itemId: id,
				sale: sale
			}).then(req => {
				this.sync();
			});
		},
		saveAddress() {
			axiosApi.post('cms-order/save-address', {
				id: this.id,
				deliveryAddress: this.order.deliveryAddress,
				invoiceAddress: this.order.invoiceAddress
			}).then(req => {
				this.sync();
			});
		},
		savePacketa() {
			axiosApi.post('cms-order/set-branch-id', {
				orderId: this.id,
				branchId: this.zasilkovna.id
			}).then(req => {
				document.getElementById('packeta-selector-branch-name').innerHTML = '';
				document.getElementById('packeta-selector-branch-id').innerHTML = '';
				this.sync();
			});
		}
	}
});
