Vue.component('cms-order-document', {
	props: ['id'],
	template: `<cms-card>
	<div v-if="list === null" class="text-center my-5">
		<b-spinner></b-spinner>
	</div>
	<template v-else>
		<div v-if="list.length === 0" class="text-center my-5">
			Document list is empty.
		</div>
		<table v-else class="table table-sm cms-table-no-border-top">
			<tr>
				<th>ID</th>
				<th>Number</th>
				<th>Label</th>
				<th>Download</th>
			</tr>
			<tr v-for="file in items">
				<td>{{ file.id }}</td>
				<td>{{ file.number }}</td>
				<td>{{ file.label }}</td>
				<td>
					<a :href="file.downloadLink" target="_blank">
						<b-button variant="secondary" size="sm">download</b-button>
					</a>
				</td>
			</tr>
		</table>
	</template>
</cms-card>`,
	data() {
		return {
			list: null,
		}
	},
	created() {
		this.sync();
	},
	methods: {
		sync() {
			axiosApi.get(`cms-order/document?id=${this.id}`)
				.then(req => {
					this.list = req.data.items;
				});
		}
	}
});
