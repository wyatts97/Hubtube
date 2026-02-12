import { useForm } from 'vee-validate';
import { toTypedSchema } from '@vee-validate/zod';
import { router } from '@inertiajs/vue3';

export const useFormValidation = (schema, initialValues = {}) => {
    const form = useForm({
        validationSchema: toTypedSchema(schema),
        initialValues,
    });

    const submit = (method, url, options = {}) => {
        return form.handleSubmit((values) => {
            const { transform, onError, ...inertiaOptions } = options;
            const payload = transform ? transform(values) : values;

            router[method](url, payload, {
                ...inertiaOptions,
                onError: (errors) => {
                    form.setErrors(errors);
                    if (onError) onError(errors);
                },
            });
        });
    };

    return {
        ...form,
        submit,
    };
};
