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
				<th width="100">ID</th>
				<th width="100">Number</th>
				<th>Label</th>
				<th>Tags</th>
				<th width="100"></th>
			</tr>
			<tr v-for="file in list">
				<td>{{ file.id }}</td>
				<td>{{ file.number }}</td>
				<td>{{ file.label }}</td>
				<td>
					<template v-for="tag in file.tags">
						<span class="badge badge-primary">{{ tag }}</span>
					</template>
				</td>
				<td class="text-right">
					<a :href="file.downloadLink" target="_blank">
						<b-button variant="secondary" size="sm">Download</b-button>
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
