<template>
    <div class="prop-panel">
        <template v-if="pageSettingsActive">
            <div class="prop-header">
                <span>页面设置</span>
            </div>
            <PageSettingsPanel :page-key="pageKey" />
        </template>
        <div v-else-if="!component" class="empty">
            选择一个组件进行编辑
            <div class="empty-tip">或点击模拟器标题栏进行页面设置</div>
        </div>
        <template v-else>
            <div class="prop-header">
                <span>{{ componentLabel }}</span>
            </div>
            <el-tabs v-model="tab" stretch>
                <el-tab-pane label="内容" name="content">
                    <div class="config-panel">
                        <!-- banner -->
                        <template v-if="component.type === 'banner'">
                            <div class="config-section">轮播设置</div>
                            <div class="config-row">
                                <span class="config-label">自动播放</span>
                                <div class="config-control">
                                    <el-switch
                                        :model-value="component.props.autoplay"
                                        @change="
                                            (v: string | number | boolean) => {
                                                $emit('begin')
                                                component.props.autoplay = v as boolean
                                            }
                                        "
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">间隔(ms)</span>
                                <div class="config-control">
                                    <ConfigSliderCombo
                                        :model-value="component.props.interval"
                                        :min="500"
                                        :max="8000"
                                        :step="500"
                                        @begin="$emit('begin')"
                                        @update:model-value="
                                            (v) => (component.props.interval = v)
                                        "
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">高度(rpx)</span>
                                <div class="config-control">
                                    <ConfigSliderCombo
                                        :model-value="component.props.height"
                                        :min="100"
                                        :max="750"
                                        :step="10"
                                        @begin="$emit('begin')"
                                        @update:model-value="(v) => (component.props.height = v)"
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">指示器</span>
                                <div class="config-control">
                                    <el-radio-group
                                        :model-value="component.props.indicator_style || 'dot'"
                                        @change="
                                            (v: any) => {
                                                $emit('begin')
                                                component.props.indicator_style = v
                                            }
                                        "
                                    >
                                        <el-radio value="dot">圆点</el-radio>
                                        <el-radio value="line">短线</el-radio>
                                        <el-radio value="number">数字</el-radio>
                                        <el-radio value="none">无</el-radio>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div
                                v-if="(component.props.indicator_style || 'dot') !== 'none'"
                                class="config-row"
                            >
                                <span class="config-label">指示位置</span>
                                <div class="config-control">
                                    <el-radio-group
                                        :model-value="
                                            component.props.indicator_position || 'inside-bottom'
                                        "
                                        @change="
                                            (v: any) => {
                                                $emit('begin')
                                                component.props.indicator_position = v
                                            }
                                        "
                                    >
                                        <el-radio value="inside-bottom">内部底部</el-radio>
                                        <el-radio value="outside-bottom">外部底部</el-radio>
                                        <el-radio value="inside-right">内部右侧</el-radio>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div class="config-hint">750 设计稿单位：300 ≈ 屏幕上 150px</div>
                            <div class="config-section">轮播图片</div>
                            <div class="config-hint">建议尺寸 750×360px</div>
                            <div
                                v-for="(item, i) in component.props.items"
                                :key="i"
                                class="config-card"
                                draggable="true"
                                @dragstart="drag.onDragStart(i, $event)"
                                @dragover.prevent
                                @drop="drag.onDrop(component.props.items, i)"
                                @dragend="drag.reset()"
                            >
                                <span class="config-card__drag">&#x2807;</span>
                                <span
                                    class="config-card__close"
                                    @click="
                                        $emit('begin');
                                        component.props.items.splice(i, 1)
                                    "
                                    >×</span
                                >
                                <ImageSelect
                                    :model-value="item.image"
                                    @update:model-value="
                                        (v: string | string[]) => {
                                            $emit('begin')
                                            item.image = v
                                        }
                                    "
                                />
                                <div class="config-card__body">
                                    <el-input
                                        v-model="item.title"
                                        placeholder="标题（可选）"
                                        @focus="$emit('begin')"
                                    />
                                    <div class="config-card__link-row">
                                        <LinkPicker
                                            :model-value="item.link"
                                            @update:model-value="
                                                (v: string) => {
                                                    $emit('begin')
                                                    item.link = v
                                                }
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                            <el-button
                                type="primary"
                                class="config-add-btn"
                                @click="
                                    $emit('begin');
                                    component.props.items.push({ image: '', link: '', title: '' })
                                "
                                >+ 添加图片</el-button
                            >
                        </template>

                        <!-- nav-grid -->
                        <template v-else-if="component.type === 'nav-grid'">
                            <div class="config-section">基础设置</div>
                            <div class="config-row">
                                <span class="config-label">列数</span>
                                <div class="config-control">
                                    <el-input-number
                                        v-model="component.props.columns"
                                        :min="2"
                                        :max="6"
                                        :controls="false"
                                        @focus="$emit('begin')"
                                    />
                                </div>
                            </div>
                            <div class="config-section">导航项</div>
                            <div
                                v-for="(item, i) in component.props.items"
                                :key="i"
                                class="config-card"
                                draggable="true"
                                @dragstart="drag.onDragStart(i, $event)"
                                @dragover.prevent
                                @drop="drag.onDrop(component.props.items, i)"
                                @dragend="drag.reset()"
                            >
                                <span class="config-card__drag">&#x2807;</span>
                                <span
                                    class="config-card__close"
                                    @click="
                                        $emit('begin');
                                        component.props.items.splice(i, 1)
                                    "
                                    >×</span
                                >
                                <ImageSelect
                                    :model-value="item.icon"
                                    @update:model-value="
                                        (v: string | string[]) => {
                                            $emit('begin')
                                            item.icon = v
                                        }
                                    "
                                />
                                <div class="config-card__body">
                                    <el-input
                                        v-model="item.text"
                                        placeholder="文字"
                                        @focus="$emit('begin')"
                                    />
                                    <div class="config-card__link-row">
                                        <LinkPicker
                                            :model-value="item.link"
                                            @update:model-value="
                                                (v: string) => {
                                                    $emit('begin')
                                                    item.link = v
                                                }
                                            "
                                        />
                                    </div>
                                    <el-select
                                        :model-value="item.badge_key || ''"
                                        placeholder="角标"
                                        @change="
                                            (v: string) => {
                                                $emit('begin')
                                                item.badge_key = v
                                            }
                                        "
                                    >
                                        <el-option label="无角标" value="" />
                                        <el-option
                                            v-for="o in statOptions"
                                            :key="o.key"
                                            :label="o.label"
                                            :value="o.key"
                                        />
                                        <el-option
                                            v-if="isStaleStat(item.badge_key || '')"
                                            :label="`已失效(${item.badge_key})`"
                                            :value="item.badge_key"
                                        />
                                    </el-select>
                                </div>
                            </div>
                            <el-button
                                type="primary"
                                class="config-add-btn"
                                @click="
                                    $emit('begin');
                                    component.props.items.push({ icon: '', text: '', link: '' })
                                "
                                >+ 添加导航项</el-button
                            >
                        </template>

                        <!-- category-nav -->
                        <template v-else-if="component.type === 'category-nav'">
                            <div class="config-section">基础设置</div>
                            <div class="config-row">
                                <span class="config-label">展示样式</span>
                                <div class="config-control">
                                    <el-radio-group
                                        :model-value="component.props.style"
                                        @change="
                                            (v: string | number | boolean | undefined) => {
                                                $emit('begin')
                                                component.props.style = v
                                            }
                                        "
                                    >
                                        <el-radio value="icon-grid">图标网格</el-radio>
                                        <el-radio value="scroll">横向滚动</el-radio>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">行数</span>
                                <div class="config-control">
                                    <el-input-number
                                        v-model="component.props.rows"
                                        :min="1"
                                        :max="3"
                                        :controls="false"
                                        @focus="$emit('begin')"
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">每行列数</span>
                                <div class="config-control">
                                    <el-input-number
                                        v-model="component.props.columns"
                                        :min="3"
                                        :max="6"
                                        :controls="false"
                                        @focus="$emit('begin')"
                                    />
                                </div>
                            </div>
                            <div class="config-section">分类项目</div>
                            <div
                                v-for="(item, i) in component.props.items"
                                :key="i"
                                class="config-card"
                                draggable="true"
                                @dragstart="drag.onDragStart(i, $event)"
                                @dragover.prevent
                                @drop="drag.onDrop(component.props.items, i)"
                                @dragend="drag.reset()"
                            >
                                <span class="config-card__drag">&#x2807;</span>
                                <span
                                    class="config-card__close"
                                    @click="
                                        $emit('begin');
                                        component.props.items.splice(i, 1)
                                    "
                                    >×</span
                                >
                                <ImageSelect
                                    :model-value="item.icon"
                                    @update:model-value="
                                        (v: string | string[]) => {
                                            $emit('begin')
                                            item.icon = v
                                        }
                                    "
                                />
                                <div class="config-card__body">
                                    <el-input
                                        v-model="item.title"
                                        placeholder="分类名称"
                                        @focus="$emit('begin')"
                                    />
                                    <div class="config-card__link-row">
                                        <LinkPicker
                                            :model-value="item.link"
                                            @update:model-value="
                                                (v: string) => {
                                                    $emit('begin')
                                                    item.link = v
                                                }
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                            <el-button
                                type="primary"
                                class="config-add-btn"
                                @click="
                                    $emit('begin');
                                    component.props.items.push({ icon: '', title: '', link: '' })
                                "
                                >+ 添加分类</el-button
                            >
                        </template>

                        <!-- rich-text -->
                        <template v-else-if="component.type === 'rich-text'">
                            <div class="config-section">内容</div>
                            <el-input
                                v-model="component.props.content"
                                type="textarea"
                                :rows="6"
                                placeholder="输入富文本内容（支持 HTML）"
                                @focus="$emit('begin')"
                            />
                        </template>

                        <!-- title-bar -->
                        <template v-else-if="component.type === 'title-bar'">
                            <div class="config-section">标题设置</div>
                            <div class="config-row">
                                <span class="config-label">标题</span>
                                <div class="config-control">
                                    <el-input
                                        v-model="component.props.title"
                                        placeholder="请输入标题"
                                        @focus="$emit('begin')"
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">副标题</span>
                                <div class="config-control">
                                    <el-input
                                        v-model="component.props.subtitle"
                                        placeholder="请输入副标题"
                                        @focus="$emit('begin')"
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">对齐方式</span>
                                <div class="config-control">
                                    <el-radio-group
                                        :model-value="component.props.align"
                                        @change="
                                            (v: string | number | boolean | undefined) => {
                                                $emit('begin')
                                                component.props.align = v
                                            }
                                        "
                                    >
                                        <el-radio value="left">居左</el-radio>
                                        <el-radio value="center">居中</el-radio>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">更多文字</span>
                                <div class="config-control">
                                    <el-input
                                        v-model="component.props.more_text"
                                        placeholder="例如：查看更多"
                                        @focus="$emit('begin')"
                                    />
                                </div>
                            </div>
                            <div class="config-row config-row--top">
                                <span class="config-label">更多链接</span>
                                <div class="config-control">
                                    <LinkPicker
                                        :model-value="component.props.more_link"
                                        @update:model-value="
                                            (v: string) => {
                                                $emit('begin')
                                                component.props.more_link = v
                                            }
                                        "
                                    />
                                </div>
                            </div>
                        </template>

                        <!-- divider -->
                        <template v-else-if="component.type === 'divider'">
                            <div class="config-section">分割设置</div>
                            <div class="config-row">
                                <span class="config-label">分割类型</span>
                                <div class="config-control">
                                    <el-radio-group
                                        :model-value="component.props.type"
                                        @change="
                                            (v: string | number | boolean | undefined) => {
                                                $emit('begin')
                                                component.props.type = v
                                            }
                                        "
                                    >
                                        <el-radio value="blank">空白</el-radio>
                                        <el-radio value="line">线条</el-radio>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">高度(px)</span>
                                <div class="config-control">
                                    <ConfigSliderCombo
                                        :model-value="component.props.height"
                                        :min="1"
                                        :max="100"
                                        @begin="$emit('begin')"
                                        @update:model-value="(v) => (component.props.height = v)"
                                    />
                                </div>
                            </div>
                            <div v-if="component.props.type === 'line'" class="config-row">
                                <span class="config-label">线条颜色</span>
                                <div class="config-control">
                                    <ConfigColorField
                                        :model-value="component.props.color"
                                        reset-value="#eeeeee"
                                        @begin="$emit('begin')"
                                        @update:model-value="(v) => (component.props.color = v)"
                                    />
                                </div>
                            </div>
                        </template>

                        <!-- image-ad -->
                        <template v-else-if="component.type === 'image-ad'">
                            <div class="config-section">布局</div>
                            <div class="config-row">
                                <span class="config-label">布局</span>
                                <div class="config-control">
                                    <el-radio-group
                                        :model-value="component.props.layout"
                                        @change="
                                            (v: string | number | boolean | undefined) => {
                                                $emit('begin')
                                                component.props.layout = v
                                            }
                                        "
                                    >
                                        <el-radio value="single">单图</el-radio>
                                        <el-radio value="grid">网格</el-radio>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div v-if="component.props.layout === 'grid'" class="config-row">
                                <span class="config-label">列数</span>
                                <div class="config-control">
                                    <el-input-number
                                        v-model="component.props.columns"
                                        :min="2"
                                        :max="4"
                                        :controls="false"
                                        @focus="$emit('begin')"
                                    />
                                </div>
                            </div>
                            <div class="config-section">图片列表</div>
                            <div
                                v-for="(item, i) in component.props.items"
                                :key="i"
                                class="config-card"
                                draggable="true"
                                @dragstart="drag.onDragStart(i, $event)"
                                @dragover.prevent
                                @drop="drag.onDrop(component.props.items, i)"
                                @dragend="drag.reset()"
                            >
                                <span class="config-card__drag">&#x2807;</span>
                                <span
                                    class="config-card__close"
                                    @click="
                                        $emit('begin');
                                        component.props.items.splice(i, 1)
                                    "
                                    >×</span
                                >
                                <ImageSelect
                                    :model-value="item.image"
                                    @update:model-value="
                                        (v: string | string[]) => {
                                            $emit('begin')
                                            item.image = v
                                        }
                                    "
                                />
                                <div class="config-card__body">
                                    <el-input
                                        v-model="item.title"
                                        placeholder="名称（可选）"
                                        @focus="$emit('begin')"
                                    />
                                    <div class="config-card__link-row">
                                        <LinkPicker
                                            :model-value="item.link"
                                            @update:model-value="
                                                (v: string) => {
                                                    $emit('begin')
                                                    item.link = v
                                                }
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                            <el-button
                                type="primary"
                                class="config-add-btn"
                                @click="
                                    $emit('begin');
                                    component.props.items.push({ image: '', link: '', title: '' })
                                "
                                >+ 添加图片</el-button
                            >
                        </template>

                        <!-- image-cube -->
                        <template v-else-if="component.type === 'image-cube'">
                            <div class="config-section">排版样式</div>
                            <div class="config-row config-row--top">
                                <span class="config-label">魔方风格</span>
                                <div class="config-control">
                                    <StylePresetPicker
                                        :model-value="component.props.layout || 'row-2'"
                                        :options="CUBE_LAYOUT_PRESETS"
                                        style-key="layout"
                                        :target-props="component.props"
                                        fallback="row-2"
                                        @begin="$emit('begin')"
                                        @change="onCubeLayoutChange"
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">间距(px)</span>
                                <div class="config-control">
                                    <ConfigSliderCombo
                                        :model-value="component.props.gap"
                                        :min="0"
                                        :max="50"
                                        @begin="$emit('begin')"
                                        @update:model-value="(v) => (component.props.gap = v)"
                                    />
                                </div>
                            </div>
                            <div class="config-section">
                                图片（{{ cubeSlotCount }} 槽）
                            </div>
                            <div
                                v-for="(it, i) in component.props.items"
                                :key="i"
                                class="config-card"
                                draggable="true"
                                @dragstart="drag.onDragStart(i, $event)"
                                @dragover.prevent
                                @drop="drag.onDrop(component.props.items, i)"
                                @dragend="drag.reset()"
                            >
                                <span class="config-card__drag">&#x2807;</span>
                                <ImageSelect
                                    :model-value="it.image"
                                    @update:model-value="
                                        (v: string | string[]) => {
                                            $emit('begin')
                                            it.image = v
                                        }
                                    "
                                />
                                <div class="config-card__body">
                                    <el-input
                                        v-model="it.title"
                                        placeholder="名称（可选）"
                                        @focus="$emit('begin')"
                                    />
                                    <div class="config-card__link-row">
                                        <LinkPicker
                                            :model-value="it.link"
                                            @update:model-value="
                                                (v: string) => {
                                                    $emit('begin')
                                                    it.link = v
                                                }
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- hotzone -->
                        <template v-else-if="component.type === 'hotzone'">
                            <div class="config-section">热区设置</div>
                            <HotzoneEditor
                                :image="component.props.image || ''"
                                :areas="component.props.areas || []"
                                @begin="$emit('begin')"
                                @update:image="
                                    (v) => {
                                        component.props.image = v
                                    }
                                "
                                @update:areas="
                                    (v) => {
                                        component.props.areas = v
                                    }
                                "
                            />
                        </template>

                        <!-- search-banner -->
                        <template v-else-if="component.type === 'search-banner'">
                            <div class="config-hint">
                                建议将本组件置于页面最上方，并在「页面设置」中关闭「显示标题栏」，以获得沉浸顶栏效果。每页仅可添加一个。
                            </div>
                            <div class="config-section">组件样式</div>
                            <div class="config-row config-row--top">
                                <span class="config-label">风格</span>
                                <div class="config-control">
                                    <StylePresetPicker
                                        :model-value="normalizeSearchBannerStyle(component.props.style)"
                                        :options="SEARCH_BANNER_STYLE_PRESETS"
                                        style-key="style"
                                        :target-props="component.props"
                                        fallback="card"
                                        @begin="$emit('begin')"
                                    />
                                    <div class="config-field-hint">
                                        样式一：轮播左右留白圆角；样式二：两侧露出相邻图
                                    </div>
                                </div>
                            </div>
                            <div class="config-section">搜索设置</div>
                            <div class="config-row">
                                <span class="config-label">分类条</span>
                                <div class="config-control">
                                    <el-radio-group
                                        :model-value="component.props.show_tabs !== false"
                                        @change="
                                            (v: any) => {
                                                $emit('begin')
                                                component.props.show_tabs = v
                                            }
                                        "
                                    >
                                        <el-radio :value="true">显示</el-radio>
                                        <el-radio :value="false">隐藏</el-radio>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">吸顶</span>
                                <div class="config-control">
                                    <el-radio-group
                                        :model-value="!!component.props.sticky"
                                        @change="
                                            (v: any) => {
                                                $emit('begin')
                                                component.props.sticky = v
                                            }
                                        "
                                    >
                                        <el-radio :value="false">正常显示</el-radio>
                                        <el-radio :value="true">滚动至顶部固定</el-radio>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">左侧内容</span>
                                <div class="config-control">
                                    <el-radio-group
                                        :model-value="component.props.brand_mode || 'logo'"
                                        @change="
                                            (v: any) => {
                                                $emit('begin')
                                                component.props.brand_mode = v
                                            }
                                        "
                                    >
                                        <el-radio value="logo">Logo</el-radio>
                                        <el-radio value="text">文字</el-radio>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div
                                v-if="(component.props.brand_mode || 'logo') === 'logo'"
                                class="config-row config-row--top"
                            >
                                <span class="config-label">默认 Logo</span>
                                <div class="config-control">
                                    <ImageSelect
                                        :model-value="component.props.logo || ''"
                                        @update:model-value="
                                            (v: string | string[]) => {
                                                $emit('begin')
                                                component.props.logo = Array.isArray(v)
                                                    ? v[0] || ''
                                                    : v
                                            }
                                        "
                                    />
                                    <div class="config-field-hint">建议尺寸 200×100</div>
                                </div>
                            </div>
                            <div
                                v-if="
                                    (component.props.brand_mode || 'logo') === 'logo' &&
                                    component.props.sticky
                                "
                                class="config-row config-row--top"
                            >
                                <span class="config-label">吸顶 Logo</span>
                                <div class="config-control">
                                    <ImageSelect
                                        :model-value="component.props.logo_sticky || ''"
                                        @update:model-value="
                                            (v: string | string[]) => {
                                                $emit('begin')
                                                component.props.logo_sticky = Array.isArray(v)
                                                    ? v[0] || ''
                                                    : v
                                            }
                                        "
                                    />
                                    <div class="config-field-hint">吸顶后使用；为空则沿用默认 Logo</div>
                                </div>
                            </div>
                            <div
                                v-if="component.props.brand_mode === 'text'"
                                class="config-row"
                            >
                                <span class="config-label">品牌文字</span>
                                <div class="config-control">
                                    <el-input
                                        v-model="component.props.brand_text"
                                        placeholder="例如店铺名"
                                        maxlength="12"
                                        @focus="$emit('begin')"
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">占位文字</span>
                                <div class="config-control">
                                    <el-input
                                        v-model="component.props.placeholder"
                                        placeholder="请输入搜索词"
                                        @focus="$emit('begin')"
                                    />
                                </div>
                            </div>
                            <div class="config-row config-row--top">
                                <span class="config-label">搜索链接</span>
                                <div class="config-control">
                                    <LinkPicker
                                        :model-value="component.props.search_link || ''"
                                        @update:model-value="
                                            (v: string) => {
                                                $emit('begin')
                                                component.props.search_link = v
                                            }
                                        "
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">主题色</span>
                                <div class="config-control">
                                    <ConfigColorField
                                        :model-value="component.props.theme_color || '#ff6034'"
                                        @begin="$emit('begin')"
                                        @update:model-value="
                                            (v: string) => (component.props.theme_color = v)
                                        "
                                    />
                                    <div class="config-field-hint">用于顶部渐变/吸顶背景</div>
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">模糊背景</span>
                                <div class="config-control">
                                    <el-switch
                                        :model-value="!!component.props.blur_bg"
                                        @change="
                                            (v: string | number | boolean) => {
                                                $emit('begin')
                                                component.props.blur_bg = !!v
                                            }
                                        "
                                    />
                                    <div class="config-field-hint">H5 生效；小程序自动降级为纯色渐变</div>
                                </div>
                            </div>
                            <template v-if="component.props.show_tabs !== false">
                                <div class="config-section">分类条</div>
                                <div
                                    v-for="(tab, i) in component.props.tabs || []"
                                    :key="'tab-' + i"
                                    class="config-card"
                                >
                                    <span
                                        class="config-card__close"
                                        @click="
                                            $emit('begin');
                                            component.props.tabs.splice(i, 1)
                                        "
                                        >×</span
                                    >
                                    <div class="config-card__body">
                                        <el-input
                                            v-model="tab.text"
                                            placeholder="分类名"
                                            maxlength="8"
                                            @focus="$emit('begin')"
                                        />
                                        <div class="config-card__link-row">
                                            <LinkPicker
                                                :model-value="tab.link || ''"
                                                @update:model-value="
                                                    (v: string) => {
                                                        $emit('begin')
                                                        tab.link = v
                                                    }
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                                <el-button
                                    type="primary"
                                    class="config-add-btn"
                                    @click="
                                        $emit('begin');
                                        if (!Array.isArray(component.props.tabs))
                                            component.props.tabs = [];
                                        component.props.tabs.push({ text: '', link: '' })
                                    "
                                    >+ 添加分类</el-button
                                >
                            </template>
                            <div class="config-section">搜索热词</div>
                            <div class="config-hint">在搜索框内轮播展示；点击优先跳转热词链接，否则走搜索链接</div>
                            <div
                                v-for="(hw, i) in component.props.hotwords || []"
                                :key="'hw-' + i"
                                class="config-card"
                            >
                                <span
                                    class="config-card__close"
                                    @click="
                                        $emit('begin');
                                        component.props.hotwords.splice(i, 1)
                                    "
                                    >×</span
                                >
                                <div class="config-card__body">
                                    <el-input
                                        v-model="hw.text"
                                        placeholder="热词，最多 10 字"
                                        maxlength="10"
                                        @focus="$emit('begin')"
                                    />
                                    <div class="config-card__link-row">
                                        <LinkPicker
                                            :model-value="hw.link || ''"
                                            @update:model-value="
                                                (v: string) => {
                                                    $emit('begin')
                                                    hw.link = v
                                                }
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                            <el-button
                                type="primary"
                                class="config-add-btn"
                                @click="
                                    $emit('begin');
                                    if (!Array.isArray(component.props.hotwords))
                                        component.props.hotwords = [];
                                    component.props.hotwords.push({ text: '', link: '' })
                                "
                                >+ 添加热词</el-button
                            >
                            <div
                                v-if="(component.props.hotwords || []).length"
                                class="config-row"
                            >
                                <span class="config-label">热词间隔</span>
                                <div class="config-control">
                                    <ConfigSliderCombo
                                        :model-value="component.props.hotword_interval || 3000"
                                        :min="1500"
                                        :max="8000"
                                        :step="500"
                                        @begin="$emit('begin')"
                                        @update:model-value="
                                            (v) => (component.props.hotword_interval = v)
                                        "
                                    />
                                </div>
                            </div>
                            <div class="config-section">轮播设置</div>
                            <div class="config-row">
                                <span class="config-label">自动播放</span>
                                <div class="config-control">
                                    <el-switch
                                        :model-value="component.props.autoplay !== false"
                                        @change="
                                            (v: string | number | boolean) => {
                                                $emit('begin')
                                                component.props.autoplay = v as boolean
                                            }
                                        "
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">间隔(ms)</span>
                                <div class="config-control">
                                    <ConfigSliderCombo
                                        :model-value="component.props.interval || 3000"
                                        :min="500"
                                        :max="8000"
                                        :step="500"
                                        @begin="$emit('begin')"
                                        @update:model-value="(v) => (component.props.interval = v)"
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">高度(rpx)</span>
                                <div class="config-control">
                                    <ConfigSliderCombo
                                        :model-value="component.props.height || 360"
                                        :min="200"
                                        :max="750"
                                        :step="10"
                                        @begin="$emit('begin')"
                                        @update:model-value="(v) => (component.props.height = v)"
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">指示器</span>
                                <div class="config-control">
                                    <el-radio-group
                                        :model-value="component.props.indicator_style || 'dot'"
                                        @change="
                                            (v: any) => {
                                                $emit('begin')
                                                component.props.indicator_style = v
                                            }
                                        "
                                    >
                                        <el-radio value="dot">圆点</el-radio>
                                        <el-radio value="line">短线</el-radio>
                                        <el-radio value="number">数字</el-radio>
                                        <el-radio value="none">无</el-radio>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div class="config-section">轮播图片</div>
                            <div class="config-hint">建议尺寸 750×360px；可在单张图填写主题色覆盖渐变</div>
                            <div
                                v-for="(item, i) in component.props.items"
                                :key="i"
                                class="config-card"
                                draggable="true"
                                @dragstart="drag.onDragStart(i, $event)"
                                @dragover.prevent
                                @drop="drag.onDrop(component.props.items, i)"
                                @dragend="drag.reset()"
                            >
                                <span class="config-card__drag">&#x2807;</span>
                                <span
                                    class="config-card__close"
                                    @click="
                                        $emit('begin');
                                        component.props.items.splice(i, 1)
                                    "
                                    >×</span
                                >
                                <ImageSelect
                                    :model-value="item.image"
                                    @update:model-value="
                                        (v: string | string[]) => {
                                            $emit('begin')
                                            item.image = v
                                        }
                                    "
                                />
                                <div class="config-card__body">
                                    <div class="config-card__link-row">
                                        <LinkPicker
                                            :model-value="item.link"
                                            @update:model-value="
                                                (v: string) => {
                                                    $emit('begin')
                                                    item.link = v
                                                }
                                            "
                                        />
                                    </div>
                                    <div class="config-field-hint">本张主题色（可选）</div>
                                    <ConfigColorField
                                        :model-value="item.theme_color || ''"
                                        @begin="$emit('begin')"
                                        @update:model-value="
                                            (v: string) => (item.theme_color = v)
                                        "
                                    />
                                </div>
                            </div>
                            <el-button
                                type="primary"
                                class="config-add-btn"
                                @click="
                                    $emit('begin');
                                    component.props.items.push({
                                        image: '',
                                        link: '',
                                        title: '',
                                        theme_color: '',
                                    })
                                "
                                >+ 添加图片</el-button
                            >
                        </template>

                        <!-- video -->
                        <template v-else-if="component.type === 'video'">
                            <div class="config-section">视频设置</div>
                            <div class="config-row config-row--top">
                                <span class="config-label">视频</span>
                                <div class="config-control">
                                    <VideoSelect
                                        :model-value="component.props.src"
                                        @update:model-value="
                                            (v: string) => {
                                                $emit('begin')
                                                component.props.src = v
                                            }
                                        "
                                    />
                                </div>
                            </div>
                            <div class="config-row config-row--top">
                                <span class="config-label">封面</span>
                                <div class="config-control">
                                    <ImageSelect
                                        :model-value="component.props.poster"
                                        @update:model-value="
                                            (v: string | string[]) => {
                                                $emit('begin')
                                                component.props.poster = v
                                            }
                                        "
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">高度(px)</span>
                                <div class="config-control">
                                    <ConfigSliderCombo
                                        :model-value="component.props.height"
                                        :min="80"
                                        :max="600"
                                        @begin="$emit('begin')"
                                        @update:model-value="(v) => (component.props.height = v)"
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">自动播放</span>
                                <div class="config-control">
                                    <el-switch
                                        :model-value="component.props.autoplay"
                                        @change="
                                            (v: string | number | boolean) => {
                                                $emit('begin')
                                                component.props.autoplay = v as boolean
                                            }
                                        "
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">循环</span>
                                <div class="config-control">
                                    <el-switch
                                        :model-value="component.props.loop"
                                        @change="
                                            (v: string | number | boolean) => {
                                                $emit('begin')
                                                component.props.loop = v as boolean
                                            }
                                        "
                                    />
                                </div>
                            </div>
                        </template>

                        <!-- notice -->
                        <template v-else-if="component.type === 'notice'">
                            <div class="config-section">公告设置</div>
                            <div class="config-row config-row--top">
                                <span class="config-label">组件样式</span>
                                <div class="config-control">
                                    <StylePresetPicker
                                        :model-value="component.props.style || 'bar'"
                                        :options="NOTICE_STYLE_PRESETS"
                                        :target-props="component.props"
                                        fallback="bar"
                                        @begin="$emit('begin')"
                                    />
                                </div>
                            </div>
                            <div class="config-row config-row--top">
                                <span class="config-label">图标</span>
                                <div class="config-control">
                                    <ImageSelect
                                        :model-value="component.props.icon"
                                        @update:model-value="
                                            (v: string | string[]) => {
                                                $emit('begin')
                                                component.props.icon = v
                                            }
                                        "
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">滚动方式</span>
                                <div class="config-control">
                                    <el-radio-group
                                        :model-value="component.props.scroll_mode || 'vertical'"
                                        @change="
                                            (v: any) => {
                                                $emit('begin')
                                                component.props.scroll_mode = v
                                            }
                                        "
                                    >
                                        <el-radio value="vertical">纵向</el-radio>
                                        <el-radio value="marquee">横向跑马灯</el-radio>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">间隔(ms)</span>
                                <div class="config-control">
                                    <ConfigSliderCombo
                                        :model-value="component.props.speed"
                                        :min="1000"
                                        :max="8000"
                                        :step="500"
                                        @begin="$emit('begin')"
                                        @update:model-value="(v) => (component.props.speed = v)"
                                    />
                                </div>
                            </div>
                            <div class="config-section">公告条目</div>
                            <div
                                v-for="(it, i) in component.props.items"
                                :key="i"
                                class="config-card"
                                draggable="true"
                                @dragstart="drag.onDragStart(i, $event)"
                                @dragover.prevent
                                @drop="drag.onDrop(component.props.items, i)"
                                @dragend="drag.reset()"
                            >
                                <span class="config-card__drag">&#x2807;</span>
                                <span
                                    class="config-card__close"
                                    @click="
                                        $emit('begin');
                                        component.props.items.splice(i, 1)
                                    "
                                    >×</span
                                >
                                <div class="config-card__body">
                                    <el-input
                                        v-model="it.text"
                                        placeholder="公告文字"
                                        @focus="$emit('begin')"
                                    />
                                    <div class="config-card__link-row">
                                        <LinkPicker
                                            :model-value="it.link"
                                            @update:model-value="
                                                (v: string) => {
                                                    $emit('begin')
                                                    it.link = v
                                                }
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                            <el-button
                                type="primary"
                                class="config-add-btn"
                                @click="
                                    $emit('begin');
                                    component.props.items.push({ text: '', link: '' })
                                "
                                >+ 添加公告</el-button
                            >
                        </template>

                        <!-- search-bar -->
                        <template v-else-if="component.type === 'search-bar'">
                            <div class="config-section">搜索框设置</div>
                            <div class="config-row">
                                <span class="config-label">占位文字</span>
                                <div class="config-control">
                                    <el-input
                                        v-model="component.props.placeholder"
                                        @focus="$emit('begin')"
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">圆角(px)</span>
                                <div class="config-control">
                                    <ConfigSliderCombo
                                        :model-value="component.props.radius"
                                        :min="0"
                                        :max="50"
                                        @begin="$emit('begin')"
                                        @update:model-value="(v) => (component.props.radius = v)"
                                    />
                                </div>
                            </div>
                            <div class="config-row">
                                <span class="config-label">背景色</span>
                                <div class="config-control">
                                    <ConfigColorField
                                        :model-value="component.props.bg_color"
                                        reset-value="#f5f5f5"
                                        @begin="$emit('begin')"
                                        @update:model-value="(v) => (component.props.bg_color = v)"
                                    />
                                </div>
                            </div>
                            <div class="config-row config-row--top">
                                <span class="config-label">点击链接</span>
                                <div class="config-control">
                                    <LinkPicker
                                        :model-value="component.props.link"
                                        @update:model-value="
                                            (v: string) => {
                                                $emit('begin')
                                                component.props.link = v
                                            }
                                        "
                                    />
                                </div>
                            </div>
                        </template>

                        <!-- float-button -->
                        <template v-else-if="component.type === 'float-button'">
                            <div class="config-section">基础设置</div>
                            <div class="config-row">
                                <span class="config-label">位置</span>
                                <div class="config-control">
                                    <el-radio-group
                                        :model-value="component.props.position"
                                        @change="
                                            (v: string | number | boolean | undefined) => {
                                                $emit('begin')
                                                component.props.position = v
                                            }
                                        "
                                    >
                                        <el-radio value="right-bottom">右下</el-radio>
                                        <el-radio value="left-bottom">左下</el-radio>
                                    </el-radio-group>
                                </div>
                            </div>
                            <div class="config-section">按钮</div>
                            <div
                                v-for="(it, i) in component.props.items"
                                :key="i"
                                class="config-card"
                                draggable="true"
                                @dragstart="drag.onDragStart(i, $event)"
                                @dragover.prevent
                                @drop="drag.onDrop(component.props.items, i)"
                                @dragend="drag.reset()"
                            >
                                <span class="config-card__drag">&#x2807;</span>
                                <span
                                    class="config-card__close"
                                    @click="
                                        $emit('begin');
                                        component.props.items.splice(i, 1)
                                    "
                                    >×</span
                                >
                                <ImageSelect
                                    :model-value="it.icon"
                                    @update:model-value="
                                        (v: string | string[]) => {
                                            $emit('begin')
                                            it.icon = v
                                        }
                                    "
                                />
                                <div class="config-card__body">
                                    <el-input
                                        v-model="it.text"
                                        placeholder="文字"
                                        @focus="$emit('begin')"
                                    />
                                    <div class="config-card__link-row">
                                        <LinkPicker
                                            :model-value="it.link"
                                            @update:model-value="
                                                (v: string) => {
                                                    $emit('begin')
                                                    it.link = v
                                                }
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                            <el-button
                                type="primary"
                                class="config-add-btn"
                                @click="
                                    $emit('begin');
                                    component.props.items.push({ icon: '', text: '', link: '' })
                                "
                                >+ 添加按钮</el-button
                            >
                        </template>

                        <!-- user-info-card -->
                        <template v-else-if="component.type === 'user-info-card'">
                            <div class="config-section">用户信息卡</div>
                            <div class="config-row">
                                <span class="config-label">展示资产</span>
                                <div class="config-control">
                                    <el-switch
                                        :model-value="component.props.show_assets"
                                        @change="
                                            (v: string | number | boolean) => {
                                                $emit('begin')
                                                component.props.show_assets = v as boolean
                                            }
                                        "
                                    />
                                </div>
                            </div>
                            <template v-if="component.props.show_assets">
                                <div class="config-section">资产格</div>
                                <div
                                    v-for="(a, i) in component.props.assets"
                                    :key="i"
                                    class="config-card"
                                >
                                    <span
                                        class="config-card__close"
                                        @click="
                                            $emit('begin');
                                            component.props.assets.splice(i, 1)
                                        "
                                        >×</span
                                    >
                                    <div class="config-card__body">
                                        <el-input
                                            v-model="a.label"
                                            placeholder="名称"
                                            @focus="$emit('begin')"
                                        />
                                        <el-select
                                            :model-value="a.stat_key"
                                            placeholder="统计源"
                                            @change="
                                                (v: string) => {
                                                    $emit('begin')
                                                    a.stat_key = v
                                                }
                                            "
                                        >
                                            <el-option label="不绑定（纯入口）" value="" />
                                            <el-option
                                                v-for="o in statOptions"
                                                :key="o.key"
                                                :label="o.label"
                                                :value="o.key"
                                            />
                                            <el-option
                                                v-if="isStaleStat(a.stat_key)"
                                                :label="`已失效(${a.stat_key})`"
                                                :value="a.stat_key"
                                            />
                                        </el-select>
                                        <div class="config-card__link-row">
                                            <LinkPicker
                                                :model-value="a.link"
                                                @update:model-value="
                                                    (v: string) => {
                                                        $emit('begin')
                                                        a.link = v
                                                    }
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                                <el-button
                                    v-if="component.props.assets.length < 4"
                                    type="primary"
                                    class="config-add-btn"
                                    @click="
                                        $emit('begin');
                                        component.props.assets.push({ label: '', stat_key: '', link: '' })
                                    "
                                    >+ 添加资产格</el-button
                                >
                                <div class="config-hint">最多 4 格；不绑定统计源时数字位显示 -</div>
                            </template>
                            <div class="config-hint">
                                头像、昵称、手机号来自会员登录态；数字登录后实时拉取。
                            </div>
                        </template>

                        <!-- service-menu -->
                        <template v-else-if="component.type === 'service-menu'">
                            <div class="config-section">服务菜单</div>
                            <div
                                v-for="(it, i) in component.props.items"
                                :key="i"
                                class="config-card"
                                draggable="true"
                                @dragstart="drag.onDragStart(i, $event)"
                                @dragover.prevent
                                @drop="drag.onDrop(component.props.items, i)"
                                @dragend="drag.reset()"
                            >
                                <span class="config-card__drag">&#x2807;</span>
                                <span
                                    class="config-card__close"
                                    @click="
                                        $emit('begin');
                                        component.props.items.splice(i, 1)
                                    "
                                    >×</span
                                >
                                <ImageSelect
                                    :model-value="it.icon"
                                    @update:model-value="
                                        (v: string | string[]) => {
                                            $emit('begin')
                                            it.icon = v
                                        }
                                    "
                                />
                                <div class="config-card__body">
                                    <el-input
                                        v-model="it.text"
                                        placeholder="菜单名称"
                                        @focus="$emit('begin')"
                                    />
                                    <div class="config-card__link-row">
                                        <LinkPicker
                                            :model-value="it.link"
                                            @update:model-value="
                                                (v: string) => {
                                                    $emit('begin')
                                                    it.link = v
                                                }
                                            "
                                        />
                                    </div>
                                    <el-select
                                        :model-value="it.badge_key || ''"
                                        placeholder="角标"
                                        @change="
                                            (v: string) => {
                                                $emit('begin')
                                                it.badge_key = v
                                            }
                                        "
                                    >
                                        <el-option label="无角标" value="" />
                                        <el-option
                                            v-for="o in statOptions"
                                            :key="o.key"
                                            :label="o.label"
                                            :value="o.key"
                                        />
                                        <el-option
                                            v-if="isStaleStat(it.badge_key || '')"
                                            :label="`已失效(${it.badge_key})`"
                                            :value="it.badge_key"
                                        />
                                    </el-select>
                                </div>
                            </div>
                            <el-button
                                type="primary"
                                class="config-add-btn"
                                @click="
                                    $emit('begin');
                                    component.props.items.push({ icon: '', text: '', link: '' })
                                "
                                >+ 添加菜单项</el-button
                            >
                        </template>

                        <!-- 插件 widget：按 props_schema 通用表单 -->
                        <template v-else-if="pluginMeta">
                            <template
                                v-for="(grp, gi) in groupVisibleFields(
                                    pluginMeta.props_schema,
                                    component.props
                                )"
                                :key="'g-' + gi + '-' + grp.section"
                            >
                                <div v-if="grp.section" class="config-section">{{ grp.section }}</div>
                                <div
                                    v-else-if="gi === 0"
                                    class="config-section"
                                >
                                    {{ pluginMeta.label }} 设置
                                </div>
                                <div
                                    v-for="f in grp.fields"
                                    :key="f.key"
                                    class="config-row"
                                    :class="{
                                        'config-row--top': [
                                            'image',
                                            'link',
                                            'api-multi-select',
                                            'style-preset',
                                            'checkbox-group',
                                            'goods-picker',
                                        ].includes(f.type),
                                    }"
                                >
                                    <span class="config-label">{{ f.label }}</span>
                                    <div class="config-control">
                                        <StylePresetPicker
                                            v-if="f.type === 'style-preset'"
                                            :model-value="
                                                resolveProp(
                                                    component.props,
                                                    f.key,
                                                    pluginMeta.default_props
                                                )
                                            "
                                            :options="(f.options || []) as any"
                                            :style-key="f.key"
                                            :target-props="component.props"
                                            :fallback="
                                                String(pluginMeta.default_props?.[f.key] || '')
                                            "
                                            @begin="$emit('begin')"
                                        />
                                        <CheckboxGroupField
                                            v-else-if="f.type === 'checkbox-group'"
                                            :model-value="
                                                checkboxGroupValue(
                                                    component.props,
                                                    f,
                                                    pluginMeta.default_props
                                                )
                                            "
                                            :options="f.options || []"
                                            @begin="$emit('begin')"
                                            @update:model-value="
                                                (v) => applyCheckboxGroup(component.props, f, v)
                                            "
                                        />
                                        <GoodsPickerField
                                            v-else-if="f.type === 'goods-picker'"
                                            :model-value="component.props[f.key] || []"
                                            @begin="$emit('begin')"
                                            @update:model-value="
                                                (v) => (component.props[f.key] = v)
                                            "
                                        />
                                        <el-input-number
                                            v-else-if="f.type === 'number'"
                                            v-model="component.props[f.key]"
                                            :controls="false"
                                            @focus="$emit('begin')"
                                        />
                                        <el-radio-group
                                            v-else-if="f.type === 'radio'"
                                            :model-value="
                                                resolveProp(
                                                    component.props,
                                                    f.key,
                                                    pluginMeta.default_props
                                                )
                                            "
                                            @change="
                                                (v: any) => {
                                                    $emit('begin')
                                                    component.props[f.key] = v
                                                }
                                            "
                                        >
                                            <el-radio
                                                v-for="o in f.options || []"
                                                :key="String(o.value)"
                                                :value="o.value"
                                                >{{ o.label }}</el-radio
                                            >
                                        </el-radio-group>
                                        <el-select
                                            v-else-if="f.type === 'select'"
                                            :model-value="
                                                resolveProp(
                                                    component.props,
                                                    f.key,
                                                    pluginMeta.default_props
                                                )
                                            "
                                            :placeholder="f.placeholder || '请选择'"
                                            clearable
                                            @change="
                                                (v: any) => {
                                                    $emit('begin')
                                                    component.props[f.key] = v
                                                }
                                            "
                                        >
                                            <el-option
                                                v-for="o in f.options || []"
                                                :key="String(o.value)"
                                                :label="o.label"
                                                :value="o.value"
                                            />
                                        </el-select>
                                        <LinkPicker
                                            v-else-if="f.type === 'link'"
                                            :model-value="component.props[f.key]"
                                            @update:model-value="
                                                (v: string) => {
                                                    $emit('begin')
                                                    component.props[f.key] = v
                                                }
                                            "
                                        />
                                        <ImageSelect
                                            v-else-if="f.type === 'image'"
                                            :model-value="component.props[f.key]"
                                            @update:model-value="
                                                (v: string | string[]) => {
                                                    $emit('begin')
                                                    component.props[f.key] = v
                                                }
                                            "
                                        />
                                        <ConfigColorField
                                            v-else-if="f.type === 'color'"
                                            :model-value="component.props[f.key]"
                                            @begin="$emit('begin')"
                                            @update:model-value="
                                                (v: string) => (component.props[f.key] = v)
                                            "
                                        />
                                        <el-switch
                                            v-else-if="f.type === 'switch'"
                                            :model-value="
                                                !!resolveProp(
                                                    component.props,
                                                    f.key,
                                                    pluginMeta.default_props
                                                )
                                            "
                                            @change="
                                                (v: any) => {
                                                    $emit('begin')
                                                    component.props[f.key] = v
                                                }
                                            "
                                        />
                                        <ApiSelect
                                            v-else-if="
                                                f.type === 'api-select' ||
                                                f.type === 'api-multi-select'
                                            "
                                            :model-value="component.props[f.key]"
                                            :url="f.options_url || ''"
                                            :multiple="f.type === 'api-multi-select'"
                                            :placeholder="f.placeholder || '请选择'"
                                            :empty-value="
                                                f.empty_value === undefined ? 0 : f.empty_value
                                            "
                                            @update:model-value="
                                                (v: any) => {
                                                    $emit('begin')
                                                    component.props[f.key] = v
                                                }
                                            "
                                        />
                                        <el-input
                                            v-else
                                            v-model="component.props[f.key]"
                                            @focus="$emit('begin')"
                                        />
                                        <div v-if="f.hint" class="config-field-hint">{{ f.hint }}</div>
                                    </div>
                                </div>
                            </template>
                            <div v-if="!pluginMeta.props_schema.length" class="config-hint">
                                该插件组件无可配置项
                            </div>
                        </template>
                    </div>
                </el-tab-pane>
                <el-tab-pane label="样式" name="style">
                    <StyleConfig :component="component" @begin="$emit('begin')" />
                </el-tab-pane>
                <el-tab-pane label="高级" name="advanced">
                    <AdvancedSettingsPanel :component="component" @begin="$emit('begin')" />
                </el-tab-pane>
            </el-tabs>
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import AdvancedSettingsPanel from './components/AdvancedSettingsPanel.vue'
import ApiSelect from './components/ApiSelect.vue'
import CheckboxGroupField from './components/CheckboxGroupField.vue'
import ConfigColorField from './components/ConfigColorField.vue'
import ConfigSliderCombo from './components/ConfigSliderCombo.vue'
import GoodsPickerField from './components/GoodsPickerField.vue'
import HotzoneEditor from './components/HotzoneEditor.vue'
import LinkPicker from './components/LinkPicker.vue'
import PageSettingsPanel from './components/PageSettingsPanel.vue'
import { groupVisibleFields } from './pluginSchema'
import {
    applyCheckboxGroup,
    checkboxGroupValue,
    ensureDefaultProps,
    resolveProp
} from './propDefaults'
import StyleConfig from './StyleConfig.vue'
import { ensureCubeItems, getCubeLayout } from './cubeLayouts'
import StylePresetPicker from './StylePresetPicker.vue'
import {
    CUBE_LAYOUT_PRESETS,
    NOTICE_STYLE_PRESETS,
    normalizeSearchBannerStyle,
    SEARCH_BANNER_STYLE_PRESETS,
} from './stylePreset'
import { defaultProps, ensureUserInfoAssets, TYPE_LABELS, useEditor } from './useEditor'
import { useDragSort } from './useDragSort'
import { usePluginWidgets } from './usePluginWidgets'

const props = withDefaults(defineProps<{ component: any | null; pageKey?: string }>(), {
    pageKey: 'home'
})
const emit = defineEmits<{ (e: 'begin'): void }>()
const tab = ref('content')
const { pageSettingsActive } = useEditor()
const { metaOf, statOptions } = usePluginWidgets()
const drag = useDragSort(() => emit('begin'))
const pluginMeta = computed(() => (props.component ? metaOf(props.component.type) : null))
const componentLabel = computed(() => {
    if (!props.component) return ''
    return pluginMeta.value?.label ?? TYPE_LABELS[props.component.type] ?? props.component.type
})

const cubeSlotCount = computed(() => {
    if (props.component?.type !== 'image-cube') return 0
    const l = getCubeLayout(props.component.props?.layout || 'row-2')
    return l?.slots ?? 2
})

function isStaleStat(key: string): boolean {
    return !!key && !statOptions.value.some((o) => o.key === key)
}

function syncCubeItems(c: any) {
    if (!c || c.type !== 'image-cube') return
    const layoutId = c.props?.layout || 'row-2'
    const l = getCubeLayout(layoutId)
    const slots = l?.slots ?? 2
    c.props.layout = layoutId
    c.props.items = ensureCubeItems(c.props.items, slots)
}

function onCubeLayoutChange() {
    if (!props.component) return
    syncCubeItems(props.component)
}

watch(
    () => props.component,
    (c, prev) => {
        if (c?.id !== prev?.id && !pageSettingsActive.value) {
            tab.value = 'content'
        }
        ensureUserInfoAssets(c)
        if (!c) return
        const meta = metaOf(c.type)
        ensureDefaultProps(c, meta?.default_props || defaultProps(c.type))
        syncCubeItems(c)
    },
    { immediate: true }
)
</script>

<style scoped lang="scss">
@import './config-ui.scss';

// el-tabs__content 默认 overflow:hidden 会把卡片右上角外凸的删除按钮(top/right:-6px)截掉一半。
// 非激活 pane 为 display:none，放开溢出不会泄漏其它面板内容。
:deep(.el-tabs__content) {
    overflow: visible;
}

.prop-panel {
    padding: 0;
    height: 100%;
    display: flex;
    flex-direction: column;

    // 标题与 tab 贴容器边、满宽（对齐 Shop）；只有 tab 内容体带内边距
    > .el-tabs {
        flex: 1;
        min-height: 0;
    }

    :deep(.el-tab-pane) {
        padding: 16px;
    }
}

.prop-header {
    padding: 12px 16px;
    font-size: 14px;
    font-weight: 500;
    border-bottom: 1px solid var(--color-divider);
    flex-shrink: 0;
}

.empty {
    color: var(--color-text-tertiary);
    font-size: var(--font-size-body);
    padding: 32px 16px;
    text-align: center;
}
.empty-tip {
    margin-top: 8px;
    font-size: 12px;
    color: var(--color-text-disabled, #c0c4cc);
}
</style>
