Vue.component('cms-order-history', {
	props: ['id'],
	template: `<cms-card>
	<div v-if="statusList === null" class="text-center my-5">
		<b-spinner></b-spinner>
	</div>
	<template v-else>
		<h3>Status history</h3>
		<p v-if="statusList.length === 0" class="text-center my-5">History is empty.</p>
		<table v-else class="table table-sm cms-table-no-border-top">
			<tr>
				<th>Status</th>
				<th class="text-right">Date</th>
			</tr>
			<tr v-for="statusItem in statusList">
				<td>{{ statusItem.status }}</td>
				<td class="text-right">{{ statusItem.insertedDate }}</td>
			</tr>
		</table>

		<h3 class="mt-3">Notification history</h3>
		<p v-if="notificationList.length === 0" class="text-center my-5">History is empty.</p>
		<table v-else class="table table-sm cms-table-no-border-top">
			<tr>
				<th>Status</th>
				<th>Type</th>
				<th>Subject</th>
				<th>Content</th>
				<th class="text-right">Date</th>
			</tr>
			<tr v-for="notificationItem in notificationList">
				<td>{{ notificationItem.label }}</td>
				<td>{{ notificationItem.type }}</td>
				<td>{{ notificationItem.subject }}</td>
				<td>
					<div class="card p-1" style="font-size:10pt;background:#eee">
						<pre>{{ notificationItem.content }}</pre>
					</div>
				</td>
				<td class="text-right">{{ notificationItem.insertedDate }}</td>
			</tr>
		</table>
	</template>
</cms-card>`,
	data() {
		return {
			statusList: null,
			notificationList: null
		}
	},
	created() {
		this.sync();
	},
	methods: {
		sync() {
			axiosApi.get(`cms-order/history?id=${this.id}`)
				.then(req => {
					this.statusList = req.data.statusList;
					this.notificationList = req.data.notificationList;
				});
		}
	}
});
