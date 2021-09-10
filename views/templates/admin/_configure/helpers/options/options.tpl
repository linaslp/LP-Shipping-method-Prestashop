{extends file="helpers/options/options.tpl"}

{block name="field"}

    {if $field['type'] == 'radio_hint'}
        <div class="col-lg-9">
            {foreach $field['choices'] AS $k => $v}
                <p style="margin: 0">
                    {strip}
                        <label class="control-label" for="{$key}_{$k}">
                            <input style="vertical-align: top" type="radio" name="{$key}" id="{$key}_{$k}" value="{$k}"{if $k == $field['value']} checked="checked"{/if}{if isset($field['js'][$k])} {$field['js'][$k]}{/if}/>
                            <span {if isset($v['hint']) && !empty($v['hint'])}class="label-tooltip" data-toggle="tooltip"{else}style="margin-left: 5px"{/if} title="" data-original-title="{if isset($v['hint']) && !empty($v['hint'])}{$v['hint']}{/if}" data-html="true">{$v['title']}</span>
                        </label>
                    {/strip}
                </p>
            {/foreach}
        </div>
    {/if}

    {if $field['type'] == 'multiple_checkboxes'}
        <div class="col-lg-9">
            {foreach $field['choices'] AS $k => $v}
                <div class="checkbox">
                    {strip}
                        {assign var='selected' value=false}
                        {if is_array($field['value']) && in_array($k, $field['value'])}
                            {assign var='selected' value=true}
                        {/if}

                        <label class="col-lg-9" for="{$key}{$k}_on">
                            <input type="checkbox" name="{$key}[]" id="{$key}{$k}_on" value="{$k}"{if $selected} checked="checked"{/if}{if isset($field['js'][$k])} {$field['js'][$k]}{/if}/>
                            {$v}
                        </label>
                    {/strip}
                </div>
            {/foreach}
        </div>
    {/if}

    {$smarty.block.parent}
{/block}
