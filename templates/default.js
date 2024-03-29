Vue.component('cms-order-default', {
	template: `<div class="container-fluid">
	<div class="row mt-2">
		<div class="col">
			<table>
				<tr>
					<td>
						<h1>Order</h1>
					</td>
					<td v-if="staticFilter.loaded === true" class="px-2">
						<b-form-select size="sm" v-model="filter.group" :options="staticFilter.filterGroups" @change="sync" style="width:120px"></b-form-select>
					</td>
				</tr>
			</table>
		</div>
		<div class="col-sm-9 text-right">
			<b-button variant="secondary" v-b-modal.modal-status-manager>Status manager</b-button>
			<b-button variant="secondary" v-b-modal.modal-group-manager>Group manager</b-button>
			<b-button variant="secondary" v-b-modal.modal-rules>Rules</b-button>
			<b-button variant="success" v-b-modal.modal-create-order>New order</b-button>
		</div>
	</div>
	<cms-filter>
		<div v-if="staticFilter.loaded === false" class="text-center">
			<b-spinner small></b-spinner>
		</div>
		<b-form v-else inline class="w-100">
			<table cellpadding="0" cellspacing="0" class="w-100">
				<tr>
					<td>
						<b-form-input size="sm" v-model="filter.query" @input="sync" class="mr-3 w-100" style="max-width:300px" placeholder="Search anywhere..."></b-form-input>
						<b-form-select size="sm" v-model="filter.status" :options="staticFilter.filterStatuses" @change="sync" style="width:120px"></b-form-select>
						<b-form-select size="sm" v-model="filter.delivery" :options="staticFilter.filterDeliveries" @change="sync" style="width:128px"></b-form-select>
						<b-form-select size="sm" v-model="filter.payment" :options="staticFilter.filterPayments" @change="sync" style="width:128px"></b-form-select>
						<b-form-select size="sm" v-model="filter.orderBy" :options="staticFilter.orderByOptions" @change="sync" style="width:128px"></b-form-select>
						<b-form-datepicker size="sm" v-model="filter.dateFrom" @input="sync" style="display:inline-block !important"></b-form-datepicker>
						<b-form-datepicker size="sm" v-model="filter.dateTo" @input="sync" style="display:inline-block !important"></b-form-datepicker>
					</td>
					<td class="text-right" style="width:80px">
						<b-form-select size="sm" v-model="filter.limit" :options="limitOptions" @change="sync" style="width:80px"></b-form-select>
					</td>
				</tr>
			</table>
		</b-form>
	</cms-filter>
	<div v-if="items === null" class="text-center py-5">
		<b-spinner></b-spinner>
	</div>
	<b-card v-else-if="items.length === 0">
		<p class="text-secondary my-5 text-center">
			No orders here.
		</p>
	</b-card>
	<b-card v-else>
		<div class="row">
			<div class="col">
				Count: <b>{{ paginator.itemCount }}</b>
				| Sum of&nbsp;view:
				<span v-for="sumValue in sum" class="badge badge-light" v-html="sumValue"></span>
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
				<th>Files</th>
			</tr>
			<tr v-for="item in items" :class="{ 'table-warning': item.pinged }">
				<td>
					<b-form-checkbox v-model="item.checked"></b-form-checkbox>
				</td>
				<td>
					<a :href="link('CmsOrder:detail', {id: item.id})">{{ item.number }}</a><br>
					<span class="badge badge-secondary" v-b-tooltip.hover.left title="Last updated date">{{ item.updatedDate }}</span><br>
					<span class="badge badge-light" v-b-tooltip.hover.left title="Inserted date">{{ item.insertedDate }}</span>
					<div v-if="item.pinged">
						<span class="badge badge-danger" v-b-tooltip.hover.left title="The customer had to be notified of the forgotten payment.">PING!</span>
					</div>
				</td>
				<td :class="{ 'table-primary': item.status.code === 'new', 'table-success': item.status.code === 'paid' }">
					<b-form-select v-model="item.status.code" :options="staticFilter.statuses" size="sm" @change="changeStatus(item.id, item.status.code)" style="margin-bottom:5px;height:8px"></b-form-select>
					<span class="badge badge-secondary" :style="'background:' + item.status.color">{{ item.status.label }}</span>
				</td>
				<td :class="item.paid === false ? 'table-warning' : ''">
					<span v-if="item.paid" class="badge badge-success">PAID</span>
					<span v-else class="badge badge-warning">NOT PAID!</span>
					<div class="text-center mt-2">
						<template v-if="item.sale > 0">
							<s class="text-danger">{{ item.price }}</s><br>
							<b>{{ item.finalPrice }}</b>
						</template>
						<template v-else>
							{{ item.price }}
						</template>
					</div>
				</td>
				<td>
					<span v-if="item.customer.premium" v-b-tooltip title="Premium customer.">🌟</span>
					<span v-if="item.customer.ban" v-b-tooltip title="Customer is banned.">🚫</span>
					<a :href="link('Customer:detail', {id: item.customer.id})" target="_blank">
						{{ item.customer.firstName }}
						{{ item.customer.lastName }}
					</a>
					<br>
					<span style="font-size:10pt">{{ item.customer.email }}</span>
					<span v-if="item.customer.phone" style="font-size:10pt"><br>{{ item.customer.phone }}</span>
				</td>
				<td class="p-0">
					<p v-if="item.items.length === 0" class="text-danger my-1">No items.</p>
					<table v-else class="w-100" cellspacing="0" cellpadding="0" style="font-size:10pt">
						<tr v-for="(orderItem, orderItemId) in item.items">
							<td class="text-right" width="32" :style="orderItemId === 0 ? 'border-top:0' : ''">
								<template v-if="orderItem.count === 1">1</template>
								<span v-else class="badge badge-danger px-1 py-0" style="font-size:11pt">{{ orderItem.count }}</span>
							</td>
							<td :style="'padding:2px 0;' + (orderItemId === 0 ? 'border-top:0' : '')">{{ orderItem.name }}</td>
							<td class="text-right" :style="orderItemId === 0 ? 'border-top:0' : ''">
								<template v-if="orderItem.sale > 0">
									<s class="text-danger">{{ orderItem.price }}</s><br>
									<b>{{ orderItem.finalPrice }}</b>
								</template>
								<template v-else>
									{{ orderItem.price }}
								</template>
							</td>
						</tr>
					</table>
					<div v-if="item.notice" class="card p-1" style="font-size:10pt;background:#eee">
						<span>{{ item.notice }}</span>
					</div>
				</td>
				<td :class="{ 'table-success': item.package }">
					<template v-if="item.delivery.name">
						<span class="badge badge-secondary" :style="'background:' + item.delivery.color">{{ item.delivery.name }}</span>
						<br>
					</template>
					<div v-html="item.delivery.price"></div>
					<div v-if="item.package">
						<span class="badge badge-success">PACKAGE READY</span>
					</div>
				</td>
				<td>
					<template v-if="item.payment.name">
						<span class="badge badge-secondary" :style="'background:' + item.payment.color">{{ item.payment.name }}</span>
						<br>
					</template>
					<div v-html="item.payment.price"></div>
				</td>
				<td>
					<div v-for="document in item.documents">
						<a :href="document.url" target="_blank">{{ document.label }}</a>
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
	<b-modal id="modal-create-order" title="Create a new order" size="lg" @shown="openCreateOrder" hide-footer>
		<div v-if="customerList === null" class="text-center my-5">
			<b-spinner></b-spinner>
		</div>
		<template v-else>
			<p>The order will be created for the <b>{{ filter.group }}</b> group.</p>
			<table class="w-100 mb-3">
				<tr>
					<td width="120">Country:</td>
					<td>
						<b-form-select v-model="newOrder.country" :options="countryList" size="sm"></b-form-select>
					</td>
				</tr>
			</table>
			<b-form-input v-model="customerListSearch" @input="openCreateOrder" placeholder="Search customers..."></b-form-input>
			<template v-if="customerListSearch">
				<table class="table table-sm cms-table-no-border-top my-3">
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th></th>
					</tr>
					<tr v-for="customer in customerList">
						<td>{{ customer.id }}</td>
						<td>{{ customer.name }}</td>
						<td class="text-right">
							<b-button size="sm" class="py-0" @click="createOrder(customer.id)">Create</b-button>
						</td>
					</tr>
				</table>
				<p class="text-secondary">
					Based on the customer found, a blank order will be created for you to edit.
				</p>
			</template>
		</template>
	</b-modal>
	<b-modal id="modal-group-manager" title="Order group manager" size="lg" @shown="openGroupManager" hide-footer>
		<div v-if="groupList === null" class="text-center my-5">
			<b-spinner></b-spinner>
		</div>
		<template v-else>
			<div class="text-right mb-3">
				<b-button variant="primary" v-b-modal.modal-group-manager-new>New group</b-button>
			</div>
			<table class="table table-sm cms-table-no-border-top">
				<tr>
					<th>Name</th>
					<th>Code</th>
					<th>Active</th>
					<th>Default</th>
					<th>Next variable</th>
				</tr>
				<tr v-for="groupItem in groupList">
					<td>{{ groupItem.name }}</td>
					<td><code>{{ groupItem.code }}</code></td>
					<td>
						<b-button size="sm" :variant="groupItem.active ? 'success' : 'danger'" disabled>{{ groupItem.active ? 'YES' : 'NO' }}</b-button>
					</td>
					<td>
						<b-button size="sm" :variant="groupItem.default ? 'success' : 'danger'" disabled>{{ groupItem.default ? 'YES' : 'NO' }}</b-button>
					</td>
					<td>{{ groupItem.nextVariable }}</td>
				</tr>
			</table>
		</template>
	</b-modal>
	<b-modal id="modal-group-manager-new" title="New order group" hide-footer>
		<b-form @submit="addGroup">
			<div class="mb-3">
				Name:
				<b-form-input v-model="newGroupForm.name"></b-form-input>
			</div>
			<div class="mb-3">
				Code:
				<b-form-input v-model="newGroupForm.code"></b-form-input>
			</div>
			<b-button type="submit" variant="primary">Create</b-button>
		</b-form>
	</b-modal>
	<b-modal id="modal-status-manager" title="Order status manager" size="xl" @shown="openStatusManager" hide-footer>
		<div v-if="statusList === null" class="text-center my-5">
			<b-spinner></b-spinner>
		</div>
		<template v-else>
			<div class="row">
				<div class="col">
					<b>Regular statuses:</b>
				</div>
				<div class="col text-right">
					<b-button variant="success" size="sm" v-b-modal.modal-status-manager-new-collection>New collection</b-button>
					<b-button variant="success" size="sm" v-b-modal.modal-status-manager-new-status>New status</b-button>
					<b-button variant="danger" size="sm" v-b-modal.modal-status-manager-remove>Remove</b-button>
				</div>
			</div>
			<table class="table table-sm cms-table-no-border-top">
				<tr>
					<th>#</th>
					<th width="80">Position</th>
					<th width="130">Name /&nbsp;Code</th>
					<th>Label</th>
					<th width="40">Color</th>
					<th>Rules</th>
					<th>Handler</th>
					<th width="130"><span v-b-tooltip title="When this status occurs, automatically execute the workflow rules and redirect to the new one.">Redirect&nbsp;to</span></th>
					<th width="80"><span v-b-tooltip title="Shows active notifications. A green icon indicates that notifications are actively being sent when order is switched to this status.">Notif.</span></th>
				</tr>
				<tr v-for="statusItem in statusList">
					<td>{{ statusItem.id }}</td>
					<td>
						<b-form-input type="number" v-model="statusItem.position" size="sm"></b-form-input>
					</td>
					<td>
						<span class="badge badge-secondary" :style="'background:' + statusItem.color + ';white-space:break-spaces'">{{ statusItem.name }}</span><br>
						<code>{{ statusItem.code }}</code>
					</td>
					<td class="p-0">
						<template v-if="editedStatus !== statusItem.id">
							<button class="btn btn-secondary btn-sm py-0 my-2" @click="editedStatus=statusItem.id">Rename</button>
						</template>
						<table v-else class="table table-sm my-0" cellpadding="0" cellspacing="0">
							<tr>
								<td class="small" style="border-top:0">Internal name</td>
								<td style="border-top:0"><b-form-input v-model="statusItem.internalName" size="sm"></b-form-input></td>
							</tr>
							<tr>
								<td class="small">Label</td>
								<td><b-form-input v-model="statusItem.label" size="sm"></b-form-input></td>
							</tr>
							<tr>
								<td class="small">Public label</td>
								<td><b-form-input v-model="statusItem.publicLabel" size="sm"></b-form-input></td>
							</tr>
						</table>
					</td>
					<td>
						<b-form-input type="color" v-model="statusItem.color" size="sm"></b-form-input>
					</td>
					<td>
						<b-form-checkbox v-model="statusItem.markAsPaid" :value="true" :unchecked-value="false">
							Mark as paid
						</b-form-checkbox>
						<b-form-checkbox v-model="statusItem.createInvoice" :value="true" :unchecked-value="false">
							Create invoice
						</b-form-checkbox>
					</td>
					<td>
						<b-form-input v-model="statusItem.systemHandle" size="sm"></b-form-input>
					</td>
					<td>
						<b-form-select v-model="statusItem.redirectTo" :options="statusItem.redirectOptions" size="sm"></b-form-select>
					</td>
					<td class="text-center">
						<div v-if="statusItem.notification.length === 0" class="text-secondary small">No&nbsp;providers.</div>
						<template v-else>
							<template v-for="(notificationActive, notificationType) in statusItem.notification">
								<b-button @click="openStatusNotification(statusItem.id, notificationType)" size="sm" :variant="notificationActive ? 'success' : 'light'" class="px-0 py-0">
									<template v-if="notificationType === 'sms'">📱</template>
									<template v-else-if="notificationType === 'email'">📧</template>
									<template v-else>{{ notificationType }}</template>
								</b-button>
							</template>
						</template>
					</td>
				</tr>
			</table>
			<div class="text-right mt-3">
				<b-button variant="primary" @click="saveStatusList">Save</b-button>
			</div>
			<hr>
			<div class="mb-3">
				<b>Status collections:</b>
			</div>
			<div v-if="statusCollectionList.length === 0" class="text-secondary">
				No collections here.
			</div>
			<table v-else class="table table-sm cms-table-no-border-top">
				<tr>
					<th width="250">Code</th>
					<th width="250">Label</th>
					<th>Contain statuses</th>
				</tr>
				<tr v-for="statusCollectionItem in statusCollectionList">
					<td>
						<b-form-input v-model="statusCollectionItem.code" size="sm"></b-form-input>
					</td>
					<td>
						<b-form-input v-model="statusCollectionItem.label" size="sm"></b-form-input>
					</td>
					<td>
						<template v-for="collectionCodeItem in statusCollectionItem.codes">
							<span class="badge badge-secondary m-1" :style="'background:' + collectionCodeItem.color + ';white-space:break-spaces'">{{ collectionCodeItem.label }}</span>
						</template>
					</td>
				</tr>
			</table>
		</template>
	</b-modal>
	<b-modal id="modal-status-manager-new-status" title="New order status" hide-footer>
		<b-form @submit="addStatus">
			<div class="mb-3">
				Name:
				<b-form-input v-model="newStatus.name"></b-form-input>
			</div>
			<div class="mb-3">
				Code:
				<b-form-input v-model="newStatus.code"></b-form-input>
			</div>
			<b-button type="submit" variant="primary">Create</b-button>
		</b-form>
	</b-modal>
	<b-modal id="modal-status-manager-new-collection" title="New collection" hide-footer>
		<b-form @submit="addCollection">
			<div class="mb-3">
				Label:
				<b-form-input v-model="newStatusCollection.label"></b-form-input>
			</div>
			<div class="mb-3">
				Code:
				<b-form-input v-model="newStatusCollection.code"></b-form-input>
			</div>
			<div class="mb-3">
				Statuses:
				<b-form-checkbox-group
					v-model="newStatusCollection.statuses"
					:options="statusSelectList"
				></b-form-checkbox-group>
			</div>
			<b-button type="submit" variant="primary">Create</b-button>
		</b-form>
	</b-modal>
	<b-modal id="modal-status-manager-remove" title="Remove order status or collection" hide-footer>
		<p>
			Deleting an order status or collection of statuses is
			<strong>always a destructive operation</strong>
			that can corrupt your historical order data, so it is not available to CMS users.
		</p>
		<p>
			If you need to delete a specific status or fundamentally change the data structure,
			contact your server administrator.
		</p>
	</b-modal>
	<b-modal id="modal-rules" title="Workflow rules" size="xl" @shown="openRulesManager" hide-footer>
		<div v-if="workflowRulesList === null" class="text-center my-5">
			<b-spinner></b-spinner>
		</div>
		<div v-else-if="workflowRulesList.length === 0">
			<p>Workflow rules is <strong>super easy and smart automation workflow</strong> for managing your orders.</p>
			<p>How does it work?</p>
			<ul>
				<li>First define automated rules for frequently used order statuses in your e-shop.</li>
				<li>When an order switches to that status, a scheduled action is automatically triggered.</li>
				<li>You can also trigger events automatically by the expiration of a set period.</li>
				<li>Each administrator in your e-shop will save up to 3 hours per day and you will reduce mistakes.</li>
			</ul>
		</div>
		<template v-else>
			<table class="table table-sm cms-table-no-border-top">
				<tr>
					<th>Label</th>
					<th>Status</th>
					<th>Active</th>
					<th>Priority</th>
					<th>Definition</th>
				</tr>
				<tr v-for="rule in workflowRulesList">
					<td>{{ rule.label }}</td>
					<td><span class="badge badge-secondary">{{ rule.status }}</span></td>
					<td>{{ rule.active }}</td>
					<td>{{ rule.priority }}</td>
					<td>
						<ul>
							<li v-if="rule.newStatus !== null">
								Set new status <span class="badge badge-secondary">{{ rule.newStatus }}</span>
							</li>
							<li v-if="rule.automaticInterval !== null">
								Automatic interval <code>{{ rule.automaticInterval }}</code>
							</li>
							<li>
								Inserted date: <code>{{ rule.insertedDate }}</code>,<br>
								Active from: <code>{{ rule.activeFrom }}</code>,<br>
								<template v-if="rule.activeTo === null">
									infinity activity.
								</template>
								<template v-else>
									active to {{ rule.rule.activeTo }}.
								</template>
							</li>
							<li v-if="rule.ignoreIfPinged">
								Ignore if order has been pinged.
							</li>
							<li v-if="rule.markAsPinged">
								Mark order as pinged.
							</li>
						</ul>
					</td>
				</tr>
			</table>
		</template>
	</b-modal>
	<b-modal id="modal-status-notification" title="Order notification" size="xl" hide-footer>
		<div v-if="activeNotification.exist === null" class="text-center my-5">
			<b-spinner></b-spinner>
		</div>
		<template v-else>
			<b-alert :show="activeNotification.exist === false" variant="warning">
				This notification has not yet been saved and does not exist.
				Please write the content of the notification template and save it.
				Once created and activated, the notification will start sending out automatically.
			</b-alert>
			<b-form-input v-model="activeNotification.subject" placeholder="Subject"></b-form-input>
			<div class="row my-3">
				<div class="col">
					<b-form-textarea v-model="activeNotification.content" placeholder="Template..." rows="20"></b-form-textarea>
					<div class="small">
						To this editor, enter a generic notification template that will be rendered for each order.
						Inside the template, you can use variables in format <code>{<!-- -->{ variable }}</code>.
						See the documentation for a list of available variables.
					</div>
				</div>
				<div class="col-4">
					<table class="w-100">
						<tr v-for="documentationItem in activeNotification.documentation" style="border-bottom:1px solid #eee">
							<td valign="top" class="pr-3"><code>{{ documentationItem.name }}</code></td>
							<td valign="top" class="small">{{ documentationItem.documentation }}</td>
						</tr>
					</table>
				</div>
			</div>
			<div class="row mt-3">
				<div class="col">
					<b-button variant="primary" @click="saveNotification">Save template</b-button>
				</div>
				<div class="col">
					<b-form-checkbox v-model="activeNotification.active" :value="true" :unchecked-value="false">Is active?</b-form-checkbox>
				</div>
				<div class="col text-right">
					{{ activeNotification.insertedDate }}
				</div>
			</div>
		</template>
	</b-modal>
</div>`,
	data() {
		return {
			items: null,
			customerList: null,
			customerListSearch: '',
			sum: [],
			paginator: {
				itemsPerPage: 0,
				page: 1,
				itemCount: 0,
			},
			staticFilter: {
				loaded: false,
				statuses: [],
				defaultGroup: null,
				filterGroups: [],
				filterStatuses: [],
				filterPayments: [],
				filterDeliveries: [],
				orderByOptions: []
			},
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
			},
			newOrder: {
				country: null
			},
			newStatus: {
				code: '',
				name: ''
			},
			newStatusCollection: {
				code: '',
				label: '',
				statuses: []
			},
			countryList: null,
			groupList: null,
			statusList: null,
			statusSelectList: null,
			statusCollectionList: null,
			workflowRulesList: null,
			editedStatus: null,
			newGroupForm: {
				name: '',
				code: ''
			},
			activeNotification: {
				exist: null,
				statusId: null,
				type: null,
				subject: null,
				content: null,
				active: false,
				insertedDate: null
			}
		}
	},
	created() {
		this.loadStaticFilter();
		this.sync();
		setInterval(this.sync, 10000);
	},
	methods: {
		sync: function () {
			let query = {
				query: this.filter.query ? this.filter.query : null,
				group: this.filter.group,
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
				});
		},
		loadStaticFilter() {
			axiosApi.get('cms-order/filter')
				.then(req => {
					this.staticFilter = req.data;
					if (this.filter.group === null) {
						this.filter.group = req.data.defaultGroup;
					}
				});
		},
		changeStatus(id, status) {
			axiosApi.post('cms-order/change-status', {
				id: id,
				status: status
			}).then(() => {
				this.sync();
			});
		},
		syncPaginator() {
			setTimeout(this.sync, 50);
		},
		sendPackets() {
			axiosApi.post('cms-order/process-packet-multiple', {
				items: this.items
			}).then(() => {
				this.sync();
			});
		},
		markAll() {
			for (let i = 0; this.items[i] !== undefined; i++) {
				this.items[i].checked = true;
			}
		},
		openCreateOrder() {
			if (this.customerList === null || this.customerListSearch !== '') {
				axiosApi.get('cms-order/customer-list?query=' + this.customerListSearch)
					.then(req => {
						this.customerList = req.data.items;
						this.countryList = req.data.countries;
						if (this.newOrder.country === null) {
							let firstCountry = req.data.countries[0];
							if (firstCountry) {
								this.newOrder.country = firstCountry.value;
							}
						}
					});
			}
		},
		openGroupManager() {
			if (this.groupList !== null) {
				return;
			}
			axiosApi.get('cms-order/group-list')
				.then(req => {
					this.groupList = req.data.groups;
				});
		},
		openStatusManager() {
			if (this.statusList !== null) {
				return;
			}
			this.loadStaticFilter();
			axiosApi.get('cms-order/status-list')
				.then(req => {
					this.statusList = req.data.statuses;
					this.statusCollectionList = req.data.collections;
					this.statusSelectList = req.data.selectList;
				});
		},
		openRulesManager() {
			if (this.workflowRulesList !== null) {
				return;
			}
			axiosApi.get('cms-order/workflow-rules')
				.then(req => {
					this.workflowRulesList = req.data.events;
				});
		},
		openStatusNotification(statusId, type) {
			this.$bvModal.show('modal-status-notification');
			this.activeNotification.exist = null;
			axiosApi.get('cms-order/notification-detail?statusId=' + statusId + '&type=' + type)
				.then(req => {
					this.activeNotification = req.data;
				});
		},
		saveNotification() {
			axiosApi.post('cms-order/save-notification', {
				statusId: this.activeNotification.statusId,
				type: this.activeNotification.type,
				subject: this.activeNotification.subject,
				content: this.activeNotification.content,
				active: this.activeNotification.active
			}).then(() => {
				this.statusList = null;
				this.sync();
				this.openStatusManager();
				this.$bvModal.hide('modal-status-notification');
			});
		},
		createOrder(customerId) {
			axiosApi.post('cms-order/create-empty-order', {
				customerId: customerId,
				countryId: this.newOrder.country,
				groupId: this.filter.group
			}).then(req => {
				window.location.href = link('CmsOrder:detail', {id: req.data.id});
			});
		},
		addGroup(evt) {
			evt.preventDefault();
			axiosApi.post('cms-order/create-group', {
				name: this.newGroupForm.name,
				code: this.newGroupForm.code
			}).then(() => {
				this.groupList = null;
				this.openGroupManager();
				this.sync();
				this.$bvModal.hide('modal-group-manager-new');
			});
		},
		addStatus(evt) {
			evt.preventDefault();
			axiosApi.post('cms-order/create-status', {
				name: this.newStatus.name,
				code: this.newStatus.code
			}).then(() => {
				this.statusList = null;
				this.openStatusManager();
				this.sync();
				this.$bvModal.hide('modal-status-manager-new-status');
			});
		},
		addCollection(evt) {
			evt.preventDefault();
			axiosApi.post('cms-order/create-status-collection', {
				code: this.newStatusCollection.code,
				label: this.newStatusCollection.label,
				statuses: this.newStatusCollection.statuses
			}).then(() => {
				this.statusList = null;
				this.openStatusManager();
				this.sync();
				this.$bvModal.hide('modal-status-manager-new-collection');
			});
		},
		saveStatusList() {
			axiosApi.post('cms-order/save-status-list', {
				statusList: this.statusList
			}).then(() => {
				this.statusList = null;
				this.openStatusManager();
			});
		}
	}
});
