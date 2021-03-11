import template from './as-order-interface-overview.html.twig';

const { Criteria } = Shopware.Data;

Shopware.Component.register('as-order-interface-overview', {
    template,
    inject: [
        'repositoryFactory'
    ],
    data() {
        return {
            repository: null,
            entries: null
        };
    },
    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },
    computed: {
        columns() {
            return [{
                property: 'notificationsActivated',
                dataIndex: 'notificationsActivated',
                label: this.$t('as-order-interface.general.columnNotificationsActivated'),
                allowResize: true,
                inlineEdit: 'boolean'
            },
            {
                property: 'productNumber',
                dataIndex: 'productNumber',
                label: this.$t('as-disposition-control.general.columnProductNumber'),
                allowResize: true
            }
            ];
        }
    },
    created() {
        this.repository = this.repositoryFactory.create('as_stock_qs')
    
        this.repository
            .search(new Criteria(), Shopware.Context.api)
            .then((result) => {
                this.entries = result;
            });
    },
    methods: {
        async onUploadsAdded() {
            await this.mediaService.runUploads(this.uploadTag);
            this.reloadList();
        },

        onUploadFinished({ targetId }) {
            this.uploads = this.uploads.filter((upload) => {
                return upload.id !== targetId;
            });
        },

        onUploadFailed({ targetId }) {
            this.uploads = this.uploads.filter((upload) => {
                return targetId !== upload.id;
            });
        }
    }
});